// Pensiunkan PWA versi lama. Tidak ada handler fetch/cache atau antrean offline.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', event => event.waitUntil((async () => {
    for (const key of await caches.keys()) {
        if (/^(kopdes-|pos-)/.test(key)) await caches.delete(key);
    }
    await self.registration.unregister();
    const windows = await self.clients.matchAll({type:'window',includeUncontrolled:true});
    for(const client of windows) client.postMessage({type:'kopdes-online-only'});
})()));
