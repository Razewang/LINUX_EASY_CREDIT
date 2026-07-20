import { get, put } from '@vercel/blob';

const orderPath = (orderNo) => `orders/${orderNo}.json`;

function assertBlobConfigured() {
  if (!process.env.BLOB_READ_WRITE_TOKEN && !process.env.VERCEL) {
    throw new Error('BLOB_READ_WRITE_TOKEN is not configured');
  }
}

export async function createOrder(order) {
  assertBlobConfigured();
  await put(orderPath(order.out_trade_no), JSON.stringify(order), {
    access: 'private',
    addRandomSuffix: false,
    contentType: 'application/json',
    cacheControlMaxAge: 60,
  });
}

export async function readOrder(orderNo) {
  assertBlobConfigured();

  try {
    const result = await get(orderPath(orderNo), { access: 'private' });
    if (!result || result.statusCode !== 200 || !result.stream) return null;

    const text = await new Response(result.stream).text();
    const order = JSON.parse(text);
    return { order, etag: result.blob.etag };
  } catch (error) {
    if (error?.name === 'BlobNotFoundError') return null;
    throw error;
  }
}

export async function updateOrder(order, etag) {
  assertBlobConfigured();
  await put(orderPath(order.out_trade_no), JSON.stringify(order), {
    access: 'private',
    allowOverwrite: true,
    contentType: 'application/json',
    cacheControlMaxAge: 60,
    ...(etag ? { ifMatch: etag } : {}),
  });
}
