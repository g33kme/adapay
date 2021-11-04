<?php

namespace g33kme\adapay;

class ADAPAY {

    /**
     * Let's construct!
     */
    public  function __construct() {

    }

    /*
     * $x = hash, walletid, ada, created, death
     */
    public static function verifyPayment($x=false) {

        $walletId = !empty($x['walletid']) ? $x['walleid'] : ADAPAY_WALLETID;
        $stop[] = empty($walletId) ? 'No valid wallet id' : false;

        $ada =  $x['ada'];
        $stop[] = (!is_numeric($ada) || $ada <= 0) ? 'No valid ada amount' : false;

        $stop[] = (!is_int($x['death']) || $x['death'] <= 0) ? 'No valid death in seconds' : false;
        $stop[] = (!is_int($x['created']) || $x['created'] <= 0) ? 'No valid created timestamp' : false;

		//Check invoice death
		$expiredIn = $x['death'];
        $elapsedTime = time() - $x['created'];
        $elapsed = ($elapsedTime > $expiredIn) ? true : false;
        $stop[] = $elapsed ? '❌ Invoice is death, please simply create a new one' : false;

		//You can add more checks by you own

		$stop = ADAPAY::cleanStop($stop);
		if(!empty($stop)) {
		    print_r($stop);
		} else {

            //Checking latest Transaction with exact amomunt
            $transactions = ADAPAY::walletTransactions(array(
                'id' => $walletId,

                //The last hour
                //'start' => date(DATE_ISO8601, time()-3600)

                //check on deathts
                'start' => date(DATE_ISO8601, time()-$x['death'])
            ));
            //print_r($transactions);

            //You can maybe verify status=in_ledger ... however ^^

            //amount is in cardano ada lovelace (similar to satoshi's), 1 ADA = 1 000 000 Lovelace
            $adaLovelace = $ada * 1000000;
            $transactionAdas = array_column($transactions, 'amount');
            //print_r($transactionAdas);

            foreach($transactionAdas AS $k => $v) {
                $adas[] = $v['quantity'];
            }

            if(!in_array($adaLovelace, $adas)) {
                // echo 'Waiting for payment of '.$ada.' ADA ...';
                return array(
                    'hash' => $x['hash'],
                    'waiting' => true,
                    'received' => false,
                    'ada' => $ada,
                    'adalovelace' => $adaLovelace
                );

            } else {

                //echo '✅ Payment received';

                //Do your database updates, flag invoice as paid etc ...
                //Important: you need to flag invoice as paid

                return array(
                    'hash' => $x['hash'],
                    'waiting' => false,
                    'received' => true,
                    'ada' => $ada,
                    'adalovelace' => $adaLovelace
                );

            }

        }

    }

    /*
     * Create an invoice
     * $x = pair, amount, identify[array], death, address, walletid
     */
    public static function invoiceCreate($x=false) {

        //Check if Adapay server is available
		$stop[] = !ADAPAY::serverAlive() ? 'Paying with ADA is currently not possible, server is not alive' : false;

		$lastPrice = ADAPAY::lastPrice($x['pair']);
        $stop[] = (!is_numeric($lastPrice) || $lastPrice <=0) ? 'Can not get last ada price for pair: '.$x['pair'] : false;

        $stop[] = (!is_numeric($x['amount']) || $x['amount'] <=0) ? 'No valid fiat amount to create invoice for pair: '.$x['pair'] : false;

        $stop[] = empty($x[address]) ? 'Please set a cardano address' : false;
        $stop[] = (!is_int($x[death]) || $x[death] <=0) ? 'Please set a valid death in seconds, when your invoice expires' : false;

        if(empty($x['identify']) OR $x['identify']['type'] == 'amount') {

            if(empty($x['identify']['start'])) {
                $x['identify']['start'] = 1000;
            } else {
                $stop[] = (!is_int($x['identify']['start']) || $x['identify']['start'] <=0) ? 'Set a valid integer start number to identify your invoice via amount' : false;
            }

            if(empty($x['identify']['end'])) {
                $x['identify']['end'] = 1000;
            } else {
                $stop[] = (!is_int($x['identify']['end']) || $x['identify']['end'] <=0) ? 'Set a valid integer end number to identify your invoice via amount' : false;
            }


        }

        $stop = ADAPAY::cleanStop($stop);
		if(!empty($stop)) {
		    print_r($stop);
		} else {

            $ada = $x['amount'] / $lastPrice;

            if(empty($x['identify']) OR $x['identify']['type'] == 'amount') {

                //magic, get random float number by simple dividing, nice ^^
                $randNumber = rand($x['identify']['start'], $x['identify']['end']) / $x['identify']['end'];
            }

            $ada = $ada + $randNumber;
            $adaLovelace = $ada * 100000000;

            $hash = ADAPAY::randHash(25);

            return array(
                'hash' => $hash,
                'amount' => $x['amount'],
                'ada' => $ada,
                'adalovelace' => $adaLovelace,
                'created' => time(),
                'death' => $x['death'],
                'address' => $x['address'],
                'walletid' => $x['walletid'],
                'identify' => array(
                    'type' => $x['identify']['type'],
                    'start' => $x['identify']['start'],
                    'end' => $x['identify']['end']
            );

        }


    }

    /*
     * Get information about a cardano address
     */
    public static function addressInspect($x=false) {

        return ADAPAY::query(array(
            'method' => 'GET',
            'url' => '/addresses/'.$x['id']
        ));

    }

    /*
     * Create a cardano address
     */
    public static function addressCreate($x=false) {

        /*
         * Hello, the short answer is that: you can't.
         *
         * The long answer is written with more details in the following Cardano Improvement Proposal https://github.com/cardano-foundation/CIPs/blob/master/CIP-1852/CIP-1852.md.
         * as this affects the ability to restore the wallet from a single seed mnemonic (payments to or from imported addresses would be missed when restoring).
         * This is a technical limitation of industry standard BIP-44 on which Cardano CIP-1852 is based
         */

    }

    /*
     * Restore or create a Cardano Wallet
     */
    public static function restoreWallet($x=false) {

        /*
         * IMPORTANT DISCLAIMER: Using values other than 20 automatically makes your wallet invalid with regards to BIP-44 address discovery.
         * It means that you will not be able to fully restore your wallet in a different software which is strictly following BIP-44.
         * Beside, using large gaps is not recommended as it may induce important performance degradations. Use at your own risks.
         */
        $x['gap'] = !empty($x['gap']) ? $x['gap'] : 20;

        return ADAPAY::query(array(
            'method' => 'POST',
            'url' => '/wallets',
            'data' => array(
                'name' => $x['name'],
                'mnemonic_sentence' => $x['mnemonic'],
                'passphrase' => $x['passphrase'],
                'address_pool_gap' => $x['gap']
            )
        ));

    }

    /*
     * List transactions from a wallet
     */
    public static function walletTransactions($x=false) {

        $data['start'] = $x['start'];
        $data['end'] = $x['end'];
        $data['order'] = $x['order'];
        $data['minWithdrawal'] = $x['minwithdrawal'];

        return ADAPAY::query(array(
            'method' => 'GET',
            'url' => '/wallets/'.$x['id'].'/transactions',
            'data' => $data
        ));

    }

    /*
     * List addresses from a cardano wallet
     */
    public static function walletAdresses($x=false) {

        return ADAPAY::query(array(
            'method' => 'GET',
            'url' => '/wallets/'.$x['id'].'/addresses'
        ));

    }

    /*
     * Get information about a cardano wallet
     */
    public static function wallet($x=false) {

        return ADAPAY::query(array(
            'method' => 'GET',
            'url' => '/wallet/'.$x['id']
        ));

    }

    /*
     * List all available cardano wallets
     */
    public static function wallets() {

        return ADAPAY::query(array(
            'method' => 'GET',
            'url' => '/wallets'
        ));

    }

    /*
     * Network status of cardano-node and cardano-wallet
     */
    public static function networkStatus() {

        return ADAPAY::query(array(
            'method' => 'GET',
            'url' => '/network/information'
        ));

    }

    /*
     * Check if cardano-wallet api is alive
     */
    public static function serverAlive() {
        $data = ADAPAY::networkStatus();
        return !empty($data) ? true : false;
    }

    /*
     * Get last ADA (Cardano) price by a pair
     * like: ADAEUR, ADAUSD, ADAGBP ...
     */
    public static function lastPrice($x=false) {
        $pairRate = ADAPAY::rates(array('pair' => $x['pair']));
        return $pairRate['lastPrice'];
    }

    /*
     * Get last ADA (Cardano) price in EUR
     */
    public static function lastPriceEuro() {
        $pairRate = ADAPAY::rates(array('pair' => 'ADAEUR'));
        return $pairRate['lastPrice'];
    }

    /*
     * Get last ADA (Cardano) price in USD
     */
    public static function lastPriceDollar() {
        $pairRate = ADAPAY::rates(array('pair' => 'ADAUSD'));
        return $pairRate['lastPrice'];
    }

    /*
     * Get last ADA (Cardano) price in GBP
     */
    public static function lastPricePound() {
        $pairRate = ADAPAY::rates(array('pair' => 'ADAGBP'));
        return $pairRate['lastPrice'];
    }

    /*
     * Get rates e.g for ADAEUR via binance
     */
    public static function rates($x=false) {

        $response = ADAPAY::curl(array(
            'method' => 'GET',
            'url' => 'https://api.binance.com/api/v1/ticker/24hr'
        ));

        $rates = json_decode($response, 1);

        if(!empty($x['pair'])) {
            $k = array_search($x['pair'], array_column($rates , 'symbol'));
            return $rates[$k];
        } else {
            return $rates;
        }


    }

    /*
     */
    public static function query($x=false) {

        //print_r($x);

        $x['method'] = !empty($x['method']) ? $x['method'] : 'GET';
        $x['url'] = ADAPAY_API.$x['url'];

        $response = ADAPAY::curl(array(

            'printcurl' => false,
            'debug' => false,
            'info' => false,

            'method' => $x['method'],
            'url' => $x['url'],
            'data' => $x['data'],

            'jsonencode' => true,

            'httpheader' => array(
                'Accept: application/json',
                'Content-type: application/json'
            ),
        ));

        //echo 'Real Response: '.$response;
        return json_decode($response, 1);


    }
    
    /**
	 * cURL Request
     */
	public static function curl($x=false) {

        if($x['printcurl']) {
            print_r($x);
        }

		$curl = curl_init();

		if($x['debug']) {
		     $fp = fopen('./curl.log', 'w');
             curl_setopt($curl, CURLOPT_VERBOSE, 1);
             curl_setopt($curl, CURLOPT_STDERR, $fp);
		}


		//Async
		if($x['async']) {

			curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);

			//Do not set TIMEOUT_MS to short, script should be of course executed ;-)
			curl_setopt($curl, CURLOPT_NOSIGNAL, 1);
			curl_setopt($curl, CURLOPT_TIMEOUT_MS, 337); //l33t ^^
		}


		//Maybe create some file for sending
		//Attention! here you set the param key to "file" for sending to a remote API, you may need another key
		if(!empty($x['file'])) {
			if(is_file($x['file'])) {
				if(is_array($x['file'])) {
					//TODO

				} else {
					//Create curl file and add to data
					//echo 'File: '.$x['file'];

					$x['filemime'] = !empty($x['filemime']) ? $x['filemime'] : 'application/octet-stream';
					$x['filename'] = !empty($x['filename']) ? $x['filename'] : basename($x['file']);

					$cfile = curl_file_create($x['file'], $x['filemime'], $x['filename']);

					//maybeyou have to change the name instead of "file" ...
					$x['data']['file'] = $cfile;
				}
			}
		}

		//To set any key for file
		//Here you can set any key for file
		if(!empty($x['createfile'])) {
			if(is_file($x['createfile']['value'])) {

                $cfile = curl_file_create($x['createfile']['value'], $x['createfile']['mimetype']);
                $x['data'][$x['createfile']['key']] = $cfile;
            }
		}


		switch($x['method']) {

			case "POST":

				curl_setopt($curl, CURLOPT_POST, true);

				if(!empty($x['data'])) {

					//JSON Encode for REST API
					if($x['jsonencode']) {

                        $dataJsonEncoded = json_encode($x['data']);

						curl_setopt($curl, CURLOPT_POSTFIELDS, $dataJsonEncoded);

						if($x['printcurl']) {
                            echo $dataJsonEncoded;
                        }


					} else {
						curl_setopt($curl, CURLOPT_POSTFIELDS, $x['data']);

						if($x['printcurl']) {
                            print_r($x['data']);
                        }

					}
				}
			break;

			case "PUT":
				curl_setopt($curl, CURLOPT_PUT, 1);
			break;

			case "DELETE":
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;

			default:
				if(!empty($x['data'])) {
					$x['url'] = sprintf("%s?%s", $x['url'], http_build_query($x['data']));
				}
			break;
		}

		// Optional Authentication:
		if(!empty($x['httpauthuser'])) {
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($curl, CURLOPT_USERPWD, $x['httpauthuser'].':'.$x['httpauthpass']);
		}

		if(!empty($x['httpheader'])) {
			curl_setopt($curl, CURLOPT_HTTPHEADER, $x['httpheader']);
		}

		curl_setopt($curl, CURLOPT_URL, $x['url']);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);

		$response = curl_exec($curl);
		$errno    = curl_errno($curl);
		$errmsg   = curl_error($curl);

		if($x['info']) {
		    $info = curl_getinfo($curl);
            print_r($info);
		}

		curl_close($curl);

		// ensure the request succeeded
		if ($errno != 0) {
			//Do not die on error!
			//die('Error: '.$errno.$errmsg);
			//echo 'curl error: '.$errno.', '.$errmsg;
		}

		//echo 'RealResponse: '.$response;
		return $response;

	}

	/*
	 */
	public static function cleanStop($stop) {
		return array_unique(array_values(array_filter($stop)));
	}

    public static function randHash($lng, $numericOnly=false) {

        mt_srand(crc32(microtime()));

        if($numericOnly) {
            $b = "1234567890";
        } else {
            $b = "abcdefghijklmnpqrstuvwxyz1234567890";
        }

       $str_lng = strlen($b)-1;
       $rand= "";

       for($i=0;$i<$lng;$i++)
          $rand.= $b{mt_rand(0, $str_lng)};

       return $rand;
    }


}
?>