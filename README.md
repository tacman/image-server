# Survos Image Server

Uses flysystem and liip imagine bundle, but does NOT freeze the system if a thumbnail has not been generated.

Instead, it sends back a "server busy" status code, and submit the image to the processing queue to be generated.

By not allowing a runtime configuration, we simplify the urls, the original request is has /resolve, the actual image does not.

The application can't call image_filter directly, since that checks the cache to create the link (/resolve or not).  The the application needs a survos/image-bundle that helps with the configuration.

Applications are required to maintain a thumbnail status, which the image server gives to them in a callback. If the filter exists then the image can be called.

```bash
bin/console survos:image:upload --url=https://pictures.com/abc.jpg
bin/console survos:image:upload --path=photos/def.jpg
# response: /d4/a1/49244.jpg   size: ...
```

The application, which does NOT cache the images, needs to store this in a database.  To request thumbnails, it's 

'd4/a1/whatever'|image_server('medium')
'https://pictures.com/abc.jpg'|image_server'
'photos/def.jpg'|image_server'

should return https://image-server.survos.com/media/cache/medium/d4/a1/whatever.jpg

We won't know if this exists, though, until we've received the callback.  So before putting that on a web page, the app needs to async request the image

https://image-server.survos.com/request/small?url=pictures.com/abc.jpg&callback=myapp/callback/images-resizer-finished

NOW the cached image exists

The image bundle can get the list of available filters, or configure only certain ones, etc.



images are served from the imageserver
