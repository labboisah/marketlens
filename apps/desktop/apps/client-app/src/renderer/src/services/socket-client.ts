import { io, type Socket } from 'socket.io-client';

export function createShopSocket(baseUrl: string, path = '/socket.io'): Socket {
  return io(baseUrl, {
    path,
    transports: ['websocket'],
    autoConnect: false,
    reconnection: true,
  });
}
