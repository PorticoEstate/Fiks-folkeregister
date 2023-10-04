<?php
	/**
	 * FIKS - integrasjon.
	 * https://developers.fiks.ks.no/tjenester/register/folkeregister/
	 * https://docs.digdir.no/docs/Maskinporten/maskinporten_guide_apikonsument
	 * https://testdata.skatteetaten.no/web/testnorge/soek/freg
	 *
	 * @author Reidar Agasøster <reidar.agasoster@stavanger.kommune.no>
	 * @author Sigurd Nes <sigurdne@gmail.com>
	 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
	 * @link     https://github.com/PorticoEstate/fiks
	 */
	require __DIR__ . '/vendor/autoload.php';

	use \Firebase\JWT\JWT;

	class fiks
	{

		private
			$debug,
			$valid_api_key,
			$expire,
			$access_token;

		public function __construct( $apikey, $debug = null )
		{
			ini_set('session.cookie_samesite', 'Lax');
			session_start();

			$this->expire		 = !empty($_SESSION['expire']) ? $_SESSION['expire'] : 0;
			$this->access_token	 = !empty($_SESSION['access_token']) ? $_SESSION['access_token'] : '';
			$this->debug		 = $debug;

			$dotenv				 = Dotenv\Dotenv::createImmutable(__DIR__);
			$dotenv->load();
			$this->valid_api_key = $apikey == $_ENV['APIKEY'];
		}

		public function get_person( $fnr )
		{
			if (!$this->valid_api_key)
			{
				return "Error: Not a valid apikey\n";
			}

			if ($this->expire < time())
			{
				$this->access_token			 = $this->get_token();
				$_SESSION['access_token']	 = $this->access_token;
				$_SESSION['expire']			 = $this->expire;
			}

			$postadresse_array	 = array();
			$adressebeskyttelse	 = 'ugradert';
			$person				 = $this->_get_person("/v1/personer/{$fnr}", $this->access_token);

			if (!$person)
			{
				return "Error: Person not found\n";
			}

			foreach ($person['navn'] as $linje)
			{
				if ($linje['erGjeldende'] == '1')
				{
					$fornavn	 = $linje['fornavn'];
					$etternavn	 = $linje['etternavn'];
				}
			}
			foreach ($person['status'] as $linje)
			{
				if ($linje['erGjeldende'] == '1')
				{
					$status = $linje['status'];
				}
			}


			if (isset($person['adressebeskyttelse']) && is_array($person['adressebeskyttelse']))
			{
				foreach ($person['adressebeskyttelse'] as $linje)
				{
					if ($linje['erGjeldende'] == '1')
					{
						$adressebeskyttelse = $linje['graderingsnivaa'];
					}
				}
			}

			if (isset($person['bostedsadresse']))
			{
				foreach ($person['bostedsadresse'] as $linje)
				{
					if ($linje['erGjeldende'] == '1')
					{
						$bokstav			 = !empty($linje['vegadresse']['adressenummer']['husbokstav']) ? $linje['vegadresse']['adressenummer']['husbokstav'] : '';
						$postadresse_array	 = array(
							$linje['vegadresse']['adressenavn'] . " " . $linje['vegadresse']['adressenummer']['husnummer'] . $bokstav,
							$linje['vegadresse']['poststed']['postnummer'] . " " . $linje['vegadresse']['poststed']['poststedsnavn']
						);
					}
				}
			}
			else if (isset($person['postadresse']) && is_array($person['postadresse']))
			{
				foreach ($person['postadresse'] as $linje)
				{
					if ($linje['erGjeldende'] == '1')
					{
						if (isset($linje['postadresseIFrittFormat']['adresselinje']))
						{
							$postadresse_array = $linje['postadresseIFrittFormat']['adresselinje'];
						}
						else
						{
							$postadresse_array = array(
								$linje['postboksadresse']['postboks'],
								$linje['postboksadresse']['poststed']['postnummer'] . " " . $linje['postboksadresse']['poststed']['poststedsnavn']
							);
						}
					}
				}
			}
			$returnobj = array(
				'fornavn'			 => $fornavn,
				'etternavn'			 => $etternavn,
				'postadresse'		 => $postadresse_array,
				'status'			 => $status,
				'adressebeskyttelse' => $adressebeskyttelse,
			);

			return (json_encode($returnobj));
		}

		private function _get_person( $path, $access_token )
		{

			$fiks_id		 = $_ENV['FIKS_ID'];
			$fiks_pwd		 = $_ENV['FIKS_PWD'];
			$fiks_rolleid	 = $_ENV['FIKS_ROLLE_ID'];

			$fiks_endpoint = $_ENV['FIKS_ENDPOINT'] . $fiks_rolleid;

			$xurl = $fiks_endpoint . $path;
			//<MILJØ_URL>/folkeregister/api/v1/{ROLLE_ID}/{FREG_RESSURS}

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_PORT			 => "443",
				CURLOPT_URL				 => $xurl,
				CURLOPT_RETURNTRANSFER	 => true,
				CURLOPT_ENCODING		 => "UTF-8",
				CURLOPT_FOLLOWLOCATION	 => true,
				CURLINFO_HEADER_OUT		 => true,
				CURLOPT_MAXREDIRS		 => 10,
				CURLOPT_TIMEOUT			 => 8,
				CURLOPT_HTTP_VERSION	 => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST	 => "GET",
				CURLOPT_HTTPHEADER		 => array(
					"cache-control: no-cache",
					"Accept: application/json",
					"IntegrasjonId:{$fiks_id}",
					"Authorization:Bearer {$access_token}",
					"IntegrasjonPassord:{$fiks_pwd}"
				)
			));

			if (!empty($_ENV['PROXY']))
			{
				curl_setopt($curl, CURLOPT_PROXY, $_ENV['PROXY']);
			}

			$response	 = curl_exec($curl);
			$httpCode	 = curl_getinfo($curl, CURLINFO_HTTP_CODE);

			if (curl_errno($curl))
			{
				$error_msg = curl_error($curl);
				_debug_array($error_msg);
				exit;
			}

			$resp = json_decode($response, true);

			if ($this->debug)
			{
				_debug_array($response);
				_debug_array($httpCode);
				_debug_array($resp);
			}

			curl_close($curl);

			return $resp;
		}

		private function get_token()
		{
			$grant_type		 = "urn:ietf:params:oauth:grant-type:jwt-bearer";
			$tokenEndpoint	 = $_ENV['IDP_TOKENENDPOINT'];
			$audience		 = $_ENV['IDP_AUDIENCE'];
			$issuer			 = $_ENV['IDP_ISSUER'];
			$scope			 = "ks:fiks";
			$cpass			 = $_ENV['CERTPASS'];
			$certfile		 = $_ENV['CERTFILE'];

			if (!$cert_store = file_get_contents($certfile))
			{
				echo "Error: Unable to read the cert file\n";
				exit;
			}

			$cert_info = array();
			if (!openssl_pkcs12_read($cert_store, $cert_info, $cpass))
			{
				echo "Error: Unable to read the cert store.\n";
				exit;
			}

			$cert		 = $cert_info['cert'];
			$privateKey	 = $cert_info['pkey'];

			$issuedAt		 = time();  // iat
			$notBefore		 = $issuedAt + 10; //nbf   Adding 10 seconds
			$this->expire	 = $notBefore + 60;   // exp  Adding 60 seconds
			$guid			 = uniqid();

			$payload = array(
				"iss"	 => $issuer,
				"scope"	 => $scope,
				"aud"	 => $audience,
				"iat"	 => $issuedAt,
				"nbf"	 => $notBefore,
				"exp"	 => $this->expire,
				"jti"	 => $guid
			);

			$crtraw = str_replace(array("\n", "\r", "-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----"), '', $cert);

			$jwt = JWT::encode($payload, $privateKey, 'RS256', null, array('x5c' => array($crtraw)));

			$data = "grant_type=" . $grant_type . "&assertion=" . $jwt;

			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_PORT			 => "443",
				CURLOPT_URL				 => $tokenEndpoint,
				CURLOPT_RETURNTRANSFER	 => true,
				CURLOPT_ENCODING		 => "UTF-8",
				CURLOPT_FOLLOWLOCATION	 => true,
				CURLINFO_HEADER_OUT		 => true,
				CURLOPT_MAXREDIRS		 => 10,
				CURLOPT_TIMEOUT			 => 8,
				CURLOPT_HTTP_VERSION	 => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST	 => "POST",
				CURLOPT_POSTFIELDS		 => $data,
				CURLOPT_HTTPHEADER		 => array(
					"cache-control: no-cache",
					"Content-Type: application/x-www-form-urlencoded")));

			if (!empty($_ENV['PROXY']))
			{
				curl_setopt($curl, CURLOPT_PROXY, $_ENV['PROXY']);
			}

			$response	 = curl_exec($curl);
			$httpCode	 = curl_getinfo($curl, CURLINFO_HTTP_CODE);
			curl_close($curl);

			if ($this->debug)
			{
				_debug_array($response);
			}
			$resp			 = json_decode($response);
			$access_token	 = $resp->access_token;
			return $access_token;
		}
	}

	function _debug_array( $obj )
	{
		echo "<pre>";
		print_r($obj);
		echo "</pre>";
	}
