/* Serohub Service Worker – Web-Push */
self.addEventListener('push', function (event) {
  var data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { body: event.data.text() };
    }
  }
  var title = data.title || 'Serohub';
  var options = {
    body: data.body || '',
    icon: data.icon || '',
    badge: data.icon || '',
    data: { url: data.url || '' },
    tag: data.tag || 'serohub-notification',
    renotify: true,
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || '/';
  event.waitUntil(
    self.clients.openWindow ? self.clients.openWindow(url) : Promise.resolve()
  );
});
