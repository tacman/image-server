# Survos Async Image Server (SAIS)

This application is based on the LiipImagineBundle, but instead of dynamically creating images on the fly, it creates them asynchronously and sends a callback to the client when finished.   It Uses flysystem so the storage is flexible.  The main purpose is to NOT freeze the system if a thumbnail has not been generated, but also have a central repository for image analysis tools.

The workflow.

* Client (e.g museado, dt-demo) registers with sais and gets an API key and code
* Via the client bundle, the client pushes urls to sais, which are queued for downloading and image creation.  A status list is returned, with codes for the URLs.
* SAIS downloads and processes the images, and for each url calls a webhook so the client application can update the database and start using the images.
* The client can also poll sais for a status, or request a single image on demand.  This is mostly for debugging, as if it's overused the server can become overwhelmed.

Applications are required to maintain a thumbnail status, which the image server gives to them in a callback. If the filter exists then the image can be called.

Also tests bad-bot, key-value.  


```bash
bin/console survos:image:upload --url=https://pictures.com/abc.jpg
bin/console survos:image:upload --path=photos/def.jpg
# response: /d4/a1/49244.jpg   size: ...
```


Instead, it sends back a "server busy" status code, and submit the image to the processing queue to be generated.

By not allowing a runtime configuration, we simplify the urls, the original request is has /resolve, the actual image does not.

The application can't call image_filter directly, since that checks the cache to create the link (/resolve or not).  The the application needs a survos/image-bundle that helps with the configuration.


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
