# autofeed

## How to use with a nginx reverse proxy

```
docker run -dt \
    --restart=unless-stopped \
    --name=feed \
    -p 127.0.0.1:8099:9000 \
    -v /path/to/public/directory:/srv/files:ro \
    -e "FEED_URL=http://example.com/feed" \
    -e "FEED_BASE_URL=https://example.com/" \
    tseho/autofeed
```

Where **FEED_URL** is the url of the RSS feed, **FEED_BASE_URL** is the url of the exposed directory and
**/path/to/public/directory** is the absolute path of this exposed directory.

```
server {
    // ...

    location /feed {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www;
        fastcgi_pass 127.0.0.1:8099;
    }
}
```
