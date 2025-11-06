# diff. base module

## Installation
- Copy module to ```/modules```
- Open ```config/app.php``` and initialize module by adding

```php
    'modules' => [
        'diffbase' => modules\diffbase\Module::class,
    ],
    'bootstrap' => ['diffbase'],
```

- Go to Settings/diff. base module and generate a API key
- Test the API Key by going to ```yourdomain.com/api/info?key=```
- Copy the API key to flow.diff.ch/admin and make a new entry with the API key and the site url 

## Compatibility
Only works/tested with Craft CMS 5.x

## Troubleshooting
If you get a error at the Login screen, make sure that in the root composer.json the modules are initialized by adding:

```json
  "autoload": {
    "psr-4": {
      "modules\\": "modules/"
    }
  },
```
