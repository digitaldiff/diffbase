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
- Copy the API key to flow.diff.ch/admin