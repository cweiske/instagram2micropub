*********************
Instagram to micropub
*********************
Downloads images of a (private) instagram profile and sends them
to your `Micropub <http://micropub.net/>`__ endpoint.

Works with private profiles you are subscribed to.

Screen scrapes the instagram web interface.

Worked at 2016-09-11.
Instagram may change their web interface - then this script will break.

Micropub endpoints that are known to work:

- `Known <https://withknown.com/>`_ (except locations and videos)


Setup
=====
0. Download and configure `shpub <https://github.com/cweiske/shpub>`_
1. Clone the repository
2. Copy ``config.php.dist`` to ``config.php``
3. Log into https://instagram.com/ in Firefox.
   Right-click the page and "inspect element"
4. Click on the "network" tab
5. Reload
6. On the first entry in that list, right-click and "copy as curl command"
7. Put the ``curl`` command parameters ``User-Agent`` and ``Cookkie`` into
   ``config.php``


Running
=======
Execute it with PHP::

    $ php extract.php

After the first successful import, change ``$stopOnFirst`` configuration
variable to ``true``.

Let that script run every 30 minutes with a cron job.


Dependencies
============
- PHP 5.5+
- `shpub <https://github.com/cweiske/shpub>`_

License
=======
AGPLv3 or later
