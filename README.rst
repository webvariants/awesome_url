===========
Awesome URL
===========

********
Features
********

- Easy configuration via backend
- Speaking URLs
- Page language by domain name or path prefix
- Old links to pages will still work if title or alias changed
- Easy migration from simulatestatic possible

*****
Usage
*****

Awesome URL extends the domain settings of Typo3 with mapping information. Only pages with a domain
and mapping information will get speaking urls.

Go to "List"-view and open a domain dataset. There you have to add at least one mapping. A mapping for a
domain consists of language and prefix.

Example domain setups
---------------------

Multiple languages, each language with its own domain
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- domain 1

 name: www.example.com

 mapping:

 - language: Default (English), prefix: '' (empty)

- domain 2

 name: www.example.de

 mapping:

 - language: German, prefix: '' (empty)

Multiple languages, one domain
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- domain 1

 name: www.example.com

 mapping:

 - language: Default (English), prefix: '' (empty)
 - language: German, prefix: 'de'
 - language: French, prefix: 'fr'

German and french URLs will be prefixed. For example http://www.example.com/de/foo/bar

One languages, one domain
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

- domain 1

 name: www.example.com

 mapping:

 - language: [All] / Default, prefix: '' (empty)

Page settings
-------------

Pages have two new settings: "URL alias" and "URL part in subpages".

If you do not like the URL derivated by the page title you can fill in the "URL alias". If you start the
alias with a slash character (/) you override the whole path from the beginning.

If you check "hide URL part in subpages" the title or alias will not be part of subpage URLs.

Template Setup
--------------

Your template must set a `base tag <http://www.w3schools.com/tags/tag_base.asp>`_
. Usually done with **config.baseURL = /** in typoscript.
