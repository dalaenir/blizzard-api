# Blizzard API

## Installation
`composer require dalaenir/blizzard-api`

## Usage
```php
require_once __DIR__ . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";
use \Dalaenir\API\Blizzard\Client;
$client = new Client($clientId, $clientSecret, $region, $locale, $redirectUri);
$result = $client->api($endpoint, $data);
```

### `Client` constructor
`$clientId`, `$clientSecret` and `$region` are required.

### `api` method
- `$endpoint` is required, which is the path used into the documentation.

- `$data` is used to define the namespace (World of Warcraft API only), give replacement values, or for Search endpoints.
For example, to retrieve data about the achievement which id is 6:
```php
$data = [
	"namespace" => "static",
	"replacement" => [
		"achievementId" => 6
	]
];
$result = $client->api("/data/wow/achievement/{achievementId}", $data);
```

`namespace` must be `static`, `dynamic` or `profile`. Check the documentation to know the good value.

- `search` is used for Search endpoints, each entry is a condition.
For example:
```php
$data = [
	"namespace" => "static",
	"search" => [
		"name.en_US=Garrosh",
		"orderby=id"
	]
];
$result = $client->api("/data/wow/search/item", $data);
```