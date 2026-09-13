// Generate a reviewed release artifact from the standalone Server's actual CSV definitions.
// Usage: node scripts/build-client-setup-kit.cjs C:/path/to/business-server
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
if (!process.argv[2]) throw new Error('Supply the standalone Business Server project path.');
const server = path.resolve(process.argv[2]);
const ts = require(path.join(server, 'node_modules/typescript'));
const sources = [
  'src/shared/csv.ts',
  'src/shared/data-transfer-contract.ts',
  'src/services/data-transfer/csv-template-library.ts',
  'src/production/templates.ts',
];
const cache = new Map();
// Fictional example dates stay reproducible, regardless of the day a pack is built.
class ExampleDate extends Date {
  constructor(...args) { super(...(args.length ? args : ['2026-01-01T00:00:00.000Z'])); }
}
function load(relative) {
  if (!sources.includes(relative)) throw new Error('Unexpected template dependency: ' + relative);
  if (cache.has(relative)) return cache.get(relative);
  const exports = {};
  cache.set(relative, exports);
  const code = ts.transpileModule(fs.readFileSync(path.join(server, relative), 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
  }).outputText;
  vm.runInNewContext(code, {
    exports, Buffer, Date: ExampleDate,
    require: (name) => load(path.posix.normalize(path.posix.join(path.posix.dirname(relative), name)) + '.ts'),
  }, { filename: relative });
  return exports;
}
const { transferDatasets } = load(sources[1]);
const { templateZip } = load(sources[2]);
const { productionTemplates } = load(sources[3]);
const { encodeCsv } = load(sources[0]);
const datasets = transferDatasets.filter((dataset) => dataset.importable);
const destination = path.join(root, 'resources/setup-kits');
fs.mkdirSync(destination, { recursive: true });
const filename = 'marketlens-client-setup-v1.zip';
const target = path.join(destination, filename);
const temporary = target + '.building';
const manifest = {
  productionDatasets: Object.entries(productionTemplates).map(([key, value]) => ({key, columns: value.columns, minimumDatabaseSchema: 5})),
  schemaVersion: 1,
  sourceApplicationVersion: JSON.parse(fs.readFileSync(path.join(server, 'package.json'), 'utf8')).version,
  compatibility: 'Business Server builds with CSV Data Setup & Transfers (schema 1). Verify feature availability; app version alone is insufficient.',
  datasets: datasets.map(({ key, label, columns, service }) => ({ key, label, optionalServiceModule: !!service, columns })),
  sourceHashes: Object.fromEntries(sources.map((source) => [source, crypto.createHash('sha256').update(fs.readFileSync(path.join(server, source))).digest('hex')])),
};
fs.writeFileSync(temporary, templateZip(datasets));
const extras = {
  'manifest.json': JSON.stringify(manifest, null, 2) + '\n',
};
for (const name of ['START-HERE.md', 'NEW-INSTALLATION.md', 'UPGRADE-CHECKLIST.md']) {
  extras[name] = fs.readFileSync(path.join(root, 'docs/setup-kit', name), 'utf8');
}
extras['guides/CSV-DATA-TRANSFERS.md'] = fs.readFileSync(path.join(server, 'docs/CSV-DATA-TRANSFERS.md'), 'utf8');
for (const [kind, template] of Object.entries(productionTemplates)) {
  extras['production/templates/'+kind+'.csv'] = encodeCsv(template.columns, []);
  extras['production/examples/'+kind+'.csv'] = encodeCsv(template.columns, [Object.fromEntries(template.columns.map((key,index)=>[key,template.example[index]]))]);
}
extras['production/README.md'] = fs.readFileSync(path.join(root, 'docs/product/production-operations.md'),'utf8');
const php = String.raw`
$zip = new ZipArchive;
if ($zip->open($argv[1]) !== true) { fwrite(STDERR, "Cannot open setup ZIP"); exit(1); }
$entries = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach ($entries as $name => $content) {
    if (!$zip->addFromString($name, $content)) { exit(2); }
    $zip->setMtimeName($name, 315532800);
}
if (!$zip->close()) { exit(3); }
`;
try {
  const result = spawnSync(process.env.PHP_BINARY || 'php', ['-r', php, temporary], {
    input: JSON.stringify(extras), encoding: 'utf8', windowsHide: true,
  });
  if (result.error || result.status !== 0) throw new Error(result.error?.message || result.stderr || 'ZIP generation failed');
  const bytes = fs.readFileSync(temporary);
  const release = { ...manifest, filename, bytes: bytes.length, sha256: crypto.createHash('sha256').update(bytes).digest('hex') };
  if (process.argv.includes('--check')) {
    const current = fs.existsSync(target) ? fs.readFileSync(target) : Buffer.alloc(0);
    const manifestPath = path.join(destination, 'manifest.json');
    if (!current.equals(bytes) || !fs.existsSync(manifestPath) || fs.readFileSync(manifestPath, 'utf8') !== JSON.stringify(release, null, 2) + '\n') {
      throw new Error('Setup pack is stale. Rebuild it from this Server source and review the artifact.');
    }
    console.log('Setup pack matches the Server definitions and guides.');
    return;
  }
  fs.renameSync(temporary, target);
  fs.writeFileSync(path.join(destination, 'manifest.json'), JSON.stringify(release, null, 2) + '\n');
  console.log(`Built ${filename}: ${datasets.length + Object.keys(productionTemplates).length} template sets, ${bytes.length} bytes.`);
} finally {
  if (fs.existsSync(temporary)) fs.unlinkSync(temporary);
}
