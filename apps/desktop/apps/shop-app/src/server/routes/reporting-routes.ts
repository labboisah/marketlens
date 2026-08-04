import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify';
import { z } from 'zod';
import type { ShopApplication } from '../../services/application/shop-application';
import { reportTypes, type LocalReportType } from '../../services/domain/local-reporting-service';
import { verifyAccessToken } from '../../services/security/token-service';

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/);
const filters = z.object({ from: date, to: date, branchUuid: z.string().uuid().optional() }).refine((value) => value.from <= value.to, { message: 'from must not be after to' });
const reportType = z.enum(reportTypes);

export async function registerReportingRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.get('/api/v1/dashboard/local', async (request, reply) => {
    await authorize(request, reply, app, 'dashboard.view');
    if (reply.sent) return;
    const query = z.object({ date: date.optional() }).parse(request.query);
    return { success: true, data: app.services.reporting.dashboard(query.date), meta: { branchUuid: app.environment.SHOP_BRANCH_UUID } };
  });

  server.get('/api/v1/reports/staff-performance', async (request, reply) => {
    await authorize(request, reply, app, 'staff.performance.view');
    if (reply.sent) return;
    return { success: true, data: app.services.reporting.staffPerformance(filters.parse(request.query)), meta: {} };
  });

  server.get('/api/v1/reports/:reportType', async (request, reply) => {
    await authorize(request, reply, app, 'reports.view');
    if (reply.sent) return;
    const params = z.object({ reportType }).parse(request.params);
    return { success: true, data: app.services.reporting.report(params.reportType, filters.parse(request.query)), meta: {} };
  });

  server.post('/api/v1/reports/:reportType/exports', async (request, reply) => {
    const actorUserUuid = await authorize(request, reply, app, 'reports.export');
    if (!actorUserUuid) return;
    const params = z.object({ reportType }).parse(request.params);
    const input = z.object({ filters, format: z.enum(['csv', 'pdf']) }).parse(request.body);
    const job = app.services.reporting.createReportJob(params.reportType, input.filters, input.format, actorUserUuid);
    setImmediate(() => {
      try {
        app.services.reporting.processReportJob(job.uuid);
      } catch (error) {
        app.logger.error({ error, reportJobUuid: job.uuid }, 'Local report job failed');
      }
    });
    return reply.code(202).send({ success: true, data: { ...job, status: 'queued' }, meta: {} });
  });

  server.get('/api/v1/report-jobs/:jobUuid', async (request, reply) => {
    await authorize(request, reply, app, 'reports.manage_jobs');
    if (reply.sent) return;
    const { jobUuid } = z.object({ jobUuid: z.string().uuid() }).parse(request.params);
    const job = app.database.sqlite.prepare('SELECT uuid, report_type AS reportType, format, status, result_path AS resultPath, error_message AS errorMessage, created_at AS createdAt, completed_at AS completedAt FROM report_jobs WHERE uuid = ?').get(jobUuid);
    if (!job) return reply.code(404).send({ success: false, error: { code: 'REPORT_JOB_NOT_FOUND', message: 'Report job was not found' } });
    return { success: true, data: job, meta: {} };
  });
}

async function authorize(request: FastifyRequest, reply: FastifyReply, app: ShopApplication, permission: string): Promise<string | undefined> {
  const header = request.headers.authorization;
  if (!header?.startsWith('Bearer ')) {
    reply.code(401).send({ success: false, error: { code: 'AUTH_REQUIRED', message: 'A valid access token is required' } });
    return undefined;
  }
  const payload = verifyAccessToken(header.slice(7), app.environment.SHOP_JWT_SECRET);
  const userUuid = typeof payload?.sub === 'string' ? payload.sub : undefined;
  if (!userUuid) {
    reply.code(401).send({ success: false, error: { code: 'AUTH_REQUIRED', message: 'A valid access token is required' } });
    return undefined;
  }
  const user = await app.services.users.findByUuid(userUuid);
  if (!user || user.status !== 'active') {
    reply.code(401).send({ success: false, error: { code: 'AUTH_REQUIRED', message: 'A valid access token is required' } });
    return undefined;
  }
  const allowed = app.database.sqlite.prepare(`
    SELECT 1
    FROM user_roles ur
    JOIN roles r ON r.uuid = ur.role_uuid
    LEFT JOIN role_permissions rp ON rp.role_uuid = r.uuid
    LEFT JOIN permissions p ON p.uuid = rp.permission_uuid
    WHERE ur.user_uuid = ? AND (p.key = ? OR r.name IN ('Owner', 'Administrator'))
    LIMIT 1
  `).get(userUuid, permission);
  if (!allowed) {
    reply.code(403).send({ success: false, error: { code: 'REPORT_PERMISSION_DENIED', message: 'You are not permitted to access this reporting resource' } });
    return undefined;
  }
  return userUuid;
}