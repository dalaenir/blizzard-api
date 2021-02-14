<?php
namespace Dalaenir\API\Blizzard;

use \Dalaenir\API\Blizzard\Exception\ClientException;

class Client {
	/**
	* @var string
	*/
	private $clientId;
	
	/**
	* @var string
	*/
	private $clientSecret;
	
	/**
	* @var string
	*/
	private $region;
	
	/**
	* @var string
	*/
	private $locale;
	
	/**
	* @var string
	*/
	private $redirectUri;
	
	/**
	* @var array
	*/
	private const HOST = [
		"us" => [
			"api" => "https://us.api.blizzard.com",
			"oauth" => "https://us.battle.net"
		],
		"eu" => [
			"api" => "https://eu.api.blizzard.com",
			"oauth" => "https://eu.battle.net"
		],
		"kr" => [
			"api" => "https://kr.api.blizzard.com",
			"oauth" => "https://apac.battle.net"
		],
		"tw" => [
			"api" => "https://tw.api.blizzard.com",
			"oauth" => "https://apac.battle.net"
		],
		"cn" => [
			"api" => "https://gateway.battlenet.com.cn",
			"oauth" => "https://www.battlenet.com.cn"
		]
	];
	
	/**
	* @var array
	*/
	private const LOCALES = ["en_US", "es_MX", "pt_BR", "en_GB", "es_ES", "fr_FR", "ru_RU", "de_DE", "it_IT", "ko_KR", "zh_TW", "zh_CN"];

	/**
	* @param string $clientId Required
	* @param string $clientSecret Required
	* @param string $region Required
	* @param string $locale Optional
	* @param string $redirectUri Optional
	*/
	public function __construct(string $clientId, string $clientSecret, string $region, string $locale = "", string $redirectUri = "") {
		$this->setClientId($clientId);
		$this->setClientSecret($clientSecret);
		$this->setRegion($region);
		
		if(!empty($locale)):
			$this->setLocale($locale);
		endif;
		
		if(!empty($redirectUri)):
			$this->setRedirectUri($redirectUri);
		endif;
	}

	/**
	* @param string $clientId Required
	* @return void
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	public function setClientId(string $clientId): void {
		if(!preg_match("#^[a-z0-9]{32}$#", $clientId)):
			throw new ClientException("'clientId' format is not valid.");
		endif;

		$this->clientId = $clientId;
	}

	/**
	* @param string $clientSecret Required
	* @return void
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	public function setClientSecret(string $clientSecret): void {
		if(!preg_match("#^[a-zA-Z0-9]{32}$#", $clientSecret)):
			throw new ClientException("'clientSecret' format is not valid.");
		endif;

		$this->clientSecret = $clientSecret;
	}

	/**
	* @param string $region Required
	* @return void
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	public function setRegion(string $region): void {
		if(!in_array($region, array_keys(self::HOST))):
			throw new ClientException("'region' is not valid.");
		endif;

		$this->region = $region;
	}

	/**
	* @param string $locale Required
	* @return void
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	public function setLocale(string $locale): void {
		if(!in_array($locale, self::LOCALES)):
			throw new ClientException("'locale' is not valid.");
		endif;

		$this->locale = $locale;
	}

	/**
	* @param string $redirectUri Required
	* @return void
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	public function setRedirectUri(string $redirectUri): void {
		if(!filter_var($redirectUri, FILTER_VALIDATE_URL)):
			throw new ClientException("'redirectUri' format is not valid.");
		endif;

		$this->redirectUri = $redirectUri;
	}

	/**
	* @param string $endpoint Required
	* @param array $data Optional
	* @return string
	*/
	public function api(string $endpoint, array $data = []): string {
		$endpoint = preg_replace_callback("#:[\w]+#i", [$this, "replacement"], $endpoint);

		if(array_key_exists("replacement", $data)):
			foreach($data["replacement"] AS $key => $value):
				$endpoint = str_replace("{" . $key . "}", $value, $endpoint);
			endforeach;
		endif;

		
		$queryParams = [];
		if(array_key_exists("namespace", $data)):
			$queryParams["namespace"] = $data["namespace"] . "-{$this->region}";
		endif;
		$queryParams["locale"] = $this->locale;

		if(array_key_exists("search", $data)):
			$searchParams = implode("&", $data["search"]) . "&";
		endif;

		$curlData = [
			CURLOPT_URL => self::HOST[$this->region]["api"] . $endpoint . "?" . ($searchParams ?? "") . http_build_query($queryParams),
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => ["Authorization: Bearer ". $this->getClientAccessToken()]
		];

		return $this->send($curlData);
	}

	/**
	* @param string $endpoint Required
	* @param array $data Optional
	* @return string
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	public function oauth(string $endpoint, array $data): string {
		if("/oauth/authorize" === $endpoint):
			$data["client_id"] = $this->clientId;
			$data["response_type"] = "code";
			$data["redirect_uri"] = $this->redirectUri;

			return self::HOST[$this->region]["oauth"] . $endpoint . "?" . http_build_query($data);
		elseif("/oauth/token" === $endpoint):
			if(!array_key_exists("code", $data)):
				throw new ClientException("'code' key is required.");
			endif;

			$data["redirect_uri"] = $this->redirectUri;
			$data["grant_type"] = "authorization_code";

			$curlData = [
				CURLOPT_URL => self::HOST[$this->region]["oauth"] . $endpoint,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_USERPWD => $this->clientId . ":" . $this->clientSecret,
				CURLOPT_POSTFIELDS => $data
			];

			return $this->send($curlData);
		elseif("/oauth/userinfo" === $endpoint):
			if(!array_key_exists("accessToken", $data)):
				throw new ClientException("'accessToken' key is required.");
			endif;

			$curlData = [
				CURLOPT_URL => self::HOST[$this->region]["oauth"] . $endpoint,
				CURLOPT_CUSTOMREQUEST => "GET",
				CURLOPT_HTTPHEADER => ["Authorization: Bearer {$data["accessToken"]}"]
			];

			return $this->send($curlData);
		elseif("/oauth/check_token" === $endpoint):
			if(!array_key_exists("accessToken", $data)):
				throw new ClientException("'accessToken' key is required.");
			endif;

			$curlData = [
				CURLOPT_URL => self::HOST[$this->region]["oauth"] . $endpoint,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => ["token" => $data["accessToken"]]
			];

			return $this->send($curlData);
		else:
			throw new ClientException("'endpoint' is not valid.");
		endif;
	}

	/**
	* @param array $curlData Required
	* @return string
	* @throws \Dalaenir\API\Blizzard\Exception\ClientException
	*/
	private function send(array $curlData): string {
		$curlData[CURLOPT_RETURNTRANSFER] = true;
		$curlData[CURLOPT_CAINFO] = dirname(__DIR__) . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "blizzard.cer";
		$curlData[CURLOPT_TIMEOUT] = 5;

		$curl = curl_init();
		curl_setopt_array($curl, $curlData);
		$result = curl_exec($curl);

		if(false === $result || curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200):
			throw new ClientException("Blizzard API Error: " . $result);
		endif;

		curl_close($curl);
		return $result;
	}

	/**
	* @internal
	* @param array $match Required
	* @return string
	*/
	private function replacement(array $match): string {
		return "{" . trim($match[0], ":") . "}";
	}

	/**
	* @internal
	* @return string
	*/
	private function getClientAccessToken(): string {
		$curlData = [
			CURLOPT_URL => self::HOST[$this->region]["oauth"] . "/oauth/token",
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_USERPWD => $this->clientId . ":" . $this->clientSecret,
			CURLOPT_POSTFIELDS => [
				"grant_type" => "client_credentials"
			]
		];

		return json_decode($this->send($curlData))->access_token;
	}
}