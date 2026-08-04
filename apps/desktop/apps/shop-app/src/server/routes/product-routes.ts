import { z } from 'zod';
import type { FastifyInstance } from 'fastify';
import type { ShopApplication } from '../../services/application/shop-application';

const categorySchema = z.object({
  name: z.string().min(1),
  slug: z.string().optional(),
  parentUuid: z.string().uuid().optional(),
  description: z.string().optional(),
});

const brandSchema = z.object({
  name: z.string().min(1),
  description: z.string().optional(),
});

const unitSchema = z.object({
  code: z.string().min(1),
  name: z.string().min(1),
  isBase: z.boolean().optional(),
});

const productSchema = z.object({
  sku: z.string().min(1),
  name: z.string().min(1),
  description: z.string().optional(),
  categoryUuid: z.string().uuid().optional(),
  brandUuid: z.string().uuid().optional(),
  baseUnitUuid: z.string().uuid().optional(),
  defaultPurchaseUnitUuid: z.string().uuid().optional(),
  defaultSalesUnitUuid: z.string().uuid().optional(),
  isService: z.boolean().optional(),
  isNonStock: z.boolean().optional(),
  active: z.boolean().optional(),
  minSellingPrice: z.string().optional(),
  reorderLevel: z.string().optional(),
  maxStock: z.string().optional(),
  costPrice: z.string().optional(),
  retailPrice: z.string().optional(),
  wholesalePrice: z.string().optional(),
  imagesJson: z.string().optional(),
});

const barcodeSchema = z.object({
  productUuid: z.string().uuid(),
  barcode: z.string().min(1),
});

const priceSchema = z.object({
  productUuid: z.string().uuid(),
  priceType: z.string().min(1),
  amount: z.string().min(1),
  currency: z.string().optional(),
  effectiveFrom: z.string().optional(),
});

export async function registerProductRoutes(server: FastifyInstance, app: ShopApplication): Promise<void> {
  server.post('/api/v1/products/categories', async (request) => {
    const input = categorySchema.parse(request.body);
    const category = app.services.products.createCategory(input);
    return { success: true, data: category, meta: {} };
  });

  server.get('/api/v1/products/categories', async () => {
    const categories = app.services.products.listCategories();
    return { success: true, data: categories, meta: {} };
  });

  server.post('/api/v1/products/brands', async (request) => {
    const input = brandSchema.parse(request.body);
    const brand = app.services.products.createBrand(input);
    return { success: true, data: brand, meta: {} };
  });

  server.get('/api/v1/products/brands', async () => {
    const brands = app.services.products.listBrands();
    return { success: true, data: brands, meta: {} };
  });

  server.post('/api/v1/products/units', async (request) => {
    const input = unitSchema.parse(request.body);
    const unit = app.services.products.createUnit(input);
    return { success: true, data: unit, meta: {} };
  });

  server.get('/api/v1/products/units', async () => {
    const units = app.services.products.listUnits();
    return { success: true, data: units, meta: {} };
  });

  server.post('/api/v1/products', async (request) => {
    const input = productSchema.parse(request.body);
    const product = app.services.products.createProduct(input);
    return { success: true, data: product, meta: {} };
  });

  server.get('/api/v1/products', async (request) => {
    const search = typeof request.query.search === 'string' ? request.query.search : undefined;
    const products = app.services.products.listProducts(search);
    return { success: true, data: products, meta: {} };
  });

  server.get('/api/v1/products/:uuid', async (request, reply) => {
    const uuid = request.params && (request.params as any).uuid;
    const product = app.services.products.findByUuid(uuid);
    if (!product) {
      return reply.code(404).send({ success: false, error: { code: 'NOT_FOUND', message: 'Product not found' } });
    }
    return { success: true, data: product, meta: {} };
  });

  server.post('/api/v1/products/barcodes', async (request) => {
    const input = barcodeSchema.parse(request.body);
    const barcode = app.services.products.createBarcode(input.productUuid, input.barcode);
    return { success: true, data: barcode, meta: {} };
  });

  server.post('/api/v1/products/prices', async (request) => {
    const input = priceSchema.parse(request.body);
    const price = app.services.products.createPrice(input.productUuid, input.priceType, input.amount, input.currency, input.effectiveFrom);
    return { success: true, data: price, meta: {} };
  });

  server.get('/api/v1/products/:uuid/prices', async (request, reply) => {
    const uuid = request.params && (request.params as any).uuid;
    const prices = app.services.products.listPrices(uuid);
    if (!prices) {
      return reply.code(404).send({ success: false, error: { code: 'NOT_FOUND', message: 'Product not found' } });
    }
    return { success: true, data: prices, meta: {} };
  });
}
