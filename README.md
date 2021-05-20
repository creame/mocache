# MOCache (WordPress .mo cached translations)

The WordPress gettext implementation is very slow. It uses objects that
cannot be cached into memory without reinstanting them.

MOCache generate a PHP hashtable (array) with all seen translations.
If external object cache (Redis, Memcache, Memcached, etc.) is active they are saved in transients.
If not, they are saved in temp files (OPcache can grab the values from memory).

Moreover, MOCache does lazyloading for strings that are only encountered in output.
It does not load all translations for a domain if it is not necessary.

## Installation

Drop `mo-cache.php` into `wp-content/mu-plugins` and enjoy the added speed :)

The more plugins you have the better the performance gains.

## Support

Let us know how it goes or report an issue at [Issues panel](https://github.com/creame/mocache/issues).

* Follow [@creapuntome](https://twitter.com/creapuntome) on Twitter
* Donate [https://www.paypal.me/creapuntome/](https://www.paypal.me/creapuntome/)

## Contribute

* Anyone is welcome to contribute to the plugin.
* Please merge (squash) all your changes into a single commit before you open a pull request.

## Credits

2021 [Creame](https://crea.me).

MOCache is heavily inspired and is a mix with the best of:

* ["WordPress Translation Cache"](https://github.com/pressjitsu/pomodoro) by **Pressjitsu, Inc.**
  * **pro:** create optimized hashtables with only required strings.
  * **con:** only save temp files.
* ["A faster load_textdomain"](https://gist.github.com/soderlind/610a9b24dbf95a678c3e) by **Per Søderlind**
  * **pro:** save .mo files in fast WordPress transients.
  * **con:** save full .mo files.

## License

GPLv3

## Changelog

### 1.0.0 - 20 May 2021
* Initial release
