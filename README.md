# NEM-Api-Library - fork
Forked from [NEM-Api-Library](https://github.com/namuyan/NEM-Api-Library).

## Forked release
This fork uses the same codebase but splits files into separated classes to support autoloading.

## Installation
Edit composer.json file:

```
"require": {
    "lezhnev74/nem-api-library":"dev-master"
},

"repositories": [
    {
        "type": "git",
        "url": "https://github.com/lezhnev74/NEM-Api-Library"
    }
],

```

And then call:
```
composer update
```

## Warning
As this is an alpha version - it has not reliable code. No tests, and probably there are bugs inside.
**Use only for testing/learning purposes!**



## Licence

[MIT](https://github.com/tcnksm/tool/blob/master/LICENCE)

