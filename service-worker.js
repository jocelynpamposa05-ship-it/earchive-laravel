const CACHE_NAME = 'e-archive-cache-v1';
// These are the files that will be cached upon installation.
// You should include the offline page and any critical assets.
const urlsToCache = [
  './offline.html',
  './students_systems/dashboard.php',
  './student_dashboard.css',
  './app.js',
  './img/cpsu_logo.png'
];

self.addEventListener('install', event => {
  // Perform install steps
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('Opened cache');
        // Ensure the offline page is cached.
        cache.add(new Request('./offline.html', {cache: 'reload'}));
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('fetch', event => {
  // We only want to handle GET requests
  if (event.request.method !== 'GET') {
    return;
  }

  event.respondWith(
    // Strategy: Network first, then cache, then offline page
    fetch(event.request)
      .then(function(response) {
        // Check if we received a valid response
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }

        // Clone the response because it's a one-time-use stream
        var responseToCache = response.clone();

        caches.open(CACHE_NAME)
          .then(function(cache) {
            cache.put(event.request, responseToCache);
          });

        return response;
      })
      .catch(function() {
        // If the fetch fails (user is offline), try to get from cache
        return caches.match(event.request)
          .then(function(response) {
            // If found in cache, return it. Otherwise, return the offline page.
            return response || caches.match('./offline.html');
          });
      })
  );
});

self.addEventListener('activate', event => {
  // This event is fired when the service worker is activated.
  console.log('Service worker activated.');
});