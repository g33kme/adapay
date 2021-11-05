# ADAPay
ADAPay is a free and open-source Cardano (ADA) payment processor script written in PHP. 
You need to run a [cardano-wallet](https://github.com/input-output-hk/cardano-wallet) to work with ADAPay.  

With ADAPay you can simply create invoices for cardano payments in your webshop as well as verify the incoming payments.

🙏 Help us improve this project by staking your ADA to our pool with ticker: **GEEK**  
[GeekMe Stake Pool](https://adapools.org/pool/c13debc5c24d045cf5e2d69c33ff981602ae55d8bded995a6d930836)


## 1. Installation: cardano-wallet

⭕ **Very important!**  

It's very easy to run cardano-wallet on your server, but you need to take care that your server has the minimum 
hardware requirements to run your cardano-node:

- Min 2 vCPU 
- Min 8GB of RAM (12GB Recommended)
- 50 GB of disk space (Ideally SSD)
- Good internet connection (at least 10Mbps)

If your hardware did not meet the minimum requirements your cardano-node may not start/sync 
and you can not use the API the cardano-wallet will serve nor can use ADAPay.

### Installing cardano-wallet via docker-compose

So, your server is up and running you can simply download the latest docker-compose.yml and save it to your server. 
Use [docker-compose](https://docs.docker.com/compose/) to quickly spin up cardano-wallet together with supported block producer.
Here is exemplary docker-compose.yaml combining the latest versions of **cardano-wallet** and **cardano-node**.

We running on Ubuntu 20.04 LTS

```bash
# Update operation system
apt-get update 
apt-get upgrade

# Install docker and docker-compose
apt-get install docker
apt-get install docker-compose

# Download latest docker-compose.yml 
wget https://raw.githubusercontent.com/input-output-hk/cardano-wallet/master/docker-compose.yml

# Start cardano-wallet in Background
NETWORK=mainnet docker-compose up -d
```

When your cardano-wallet is up running `NETWORK=mainnet docker-compose up -d` ou can simply check in your Browser the height of the Cardano Blockchain by requesting the following URL:  

```bash
http://<IP4-SERVER-ADDRESS>:8090/v2/network/information
```

It may take some time, maybe 1-2 days tilly our cardano-wallet is synced completely. When finish you get some "ready" status in the JSON response when browsing the above URL.

```json
{"network_tip":{"time":"2021-11-02T17:01:09Z","epoch_number":300,"absolute_slot_number":44306178,"slot_number":69378},"node_era":"alonzo","node_tip":{"height":{"quantity":6450485,"unit":"block"},"time":"2021-11-02T17:00:44Z","epoch_number":300,"absolute_slot_number":44306153,"slot_number":69353},"sync_progress":{"status":"ready"},"next_epoch":{"epoch_start_time":"2021-11-06T21:44:51Z","epoch_number":301}}
````

⭕ **Very important!**  

[cardano-wallet](https://github.com/input-output-hk/cardano-wallet) creates a public accessible [API](https://input-output-hk.github.io/cardano-wallet/api/edge/) on port 8090 (default). 
At easiest and best simple create firewall rules to access the cardano-wallet API only from specific IPs. 
You can create firewalls, mostly free, on popular hosting providers like Hetzner, Vultr or DigitalOcean. Of course you can setup a custom firewal rule 
in your operating system as well.

**Wanna run cardano-wallet API on https?** 

Simply setup a nginx proxy pass for the served API of the cardano-wallet

```bash
apt-get install nginx

# create some ssl certs
openssl req -x509 -nodes -days 5478 -newkey rsa:2048 -keyout /etc/nginx/cert.key -out /etc/nginx/cert.crt

# switch to nginx dir
cd /etc/nginx/sites-enabled

# download nginx setup for adapay proxy
wget https://raw.githubusercontent.com/g33kme/adapay/main/nginx.adapay

# check nginx config and restart
service nginx configtest
systemctl restart nginx
```

You should now be able to browse your cardano-wallet API via https/ssl. Keep in mind that we don't created valid ssl certificates, so 
you get an insecure warning in your browser. However, we don't verify this on ADAPay.

```bash
https://<IP4-SERVER-ADDRESS>/v2/network/information
```

You may also take care to update your firewall settings and block public traffic for Port 443.
  

## 2. Installation: ADAPay

Alright, your cardano-wallet is up and running. Let's have a look to our ADAPay library to easily create invoices and check payments.

This library is installable via [Composer](https://getcomposer.org/):

```bash
composer require g33kme/adapay
```

You can also simply manually include `source/adapay.php` from this repository to your project and use the ADAPay PHP Class.

```php
include('path/to/my/adapay.php');
```

## Requirements

This library requires PHP 7.1 or later and if you use Composer you need Version 2+.


## 3. How to use ADApay
With the ADAPay PHP library it's very easy to work with your cardano-wallet API. Check your wallet, craete invoices, check receiving payments ...

```php
/*
 * You have to setup some Settings
 */

// Define your URL to API, with version, but NO ending slash!
define('ADAPAY_API', 'http://<IP4-SERVER-ADDRESS>:8090/v2');

//Optional, you can define an walletid, but you can also pass a walletid in the parameters
define('ADAPAY_WALLETID', '129328d18339990c7398e02975c4513754881337');
```

### Create or Restore Cardano Wallet

```php
// Define your URL to API
define('ADAPAY_API', 'http://<IP4-SERVER-ADDRESS>:8090/v2');

/*
 * First we need to create or restore a wallet on our cardano-wallet server
 * Keep in mind, that the cardano-node have to be fully synced so your wallet comes up
 * 
 * name              = set any name 
 * mnemonic_sentence = create new or add your existing mnemonic recovery phrase to restore a wallet, 24 words for shelly wallet
 * passphrase        = set a password
 * 
 * You will get an "id" back, save this unique id to use the wallet for ADAPay
 */

$wallet = ADAPAY::restoreWallet(array(
    'name' => 'adapay',
    'mnemonic' => 'that are just twenty four words that have no meaning and only for adapay as placeholder so dont try to copy this cheers adapay'
    'passphrase' => 'myAwesomeAdaPayPassword',
));
print_r($wallet);

/*
 * If you already created a wallet you can simply show all created wallets and grab your walletid
 */
$wallets = ADAPAY::wallets(array();
print_r($wallets);

/*
 * List all you receiving wallet cardano addresses
 * Use one  from the list to receive your payments on and create invoices
 */
$addresses = ADAPAY::walletAdresses();
print_r($addresses);

/*
 * Check current network Status, Cardano Node height
 */
$networkStatus = ADAPAY::networkStatus();
print_r($networkStatus);

```

### Create an invoice
```php
// Define your URL to API
define('ADAPAY_API', 'http://<IP4-SERVER-ADDRESS>:8090/v2');

/*
 * How to create an invoice
 * 
 * pair     = set a pair like ADAUSD, ADAEUR, ADAGBP
 * amount   = fiat amount, based on the pair your ada amount for the invoice will be calculated
 * identify = how to identify the paid invoice
 * death    = in seconds when the invoice expires
 * address  = set one of your cardano addresses from your created wallet where you want to receive the payment
 * walletid = set a walletid from your cardano-wallet you created in the first step
 * 
 * Each created cardano invoice return an unique hash and the calculated ADA price from your fiat amount
 * We recommend to save your invoice in a database to flag them as paid if you received it
 * 
 * Currently ADAPay will create an unique number on the payment amount to identify incoming payment
 * Creating a new Cardano addresse for each invoice is currently not easy with the Cardano Wallet API, we may provide this in the future 
 */
$invoice = ADAPAY::invoiceCreate(array(
   'pair' => 'ADAUSD',
   'amount' => 10,
   'identify' => array(
        'type' => 'amount'
   ), 
   'death' => 1800,
   'address' => 'addr1qxksn95zhgje7tvdsgfpk9t49sssz4fqewt74neh56cnl4ml8zpc3556jh8exfp70a6f3pva7yf4fmfmw52tdh3dh94sqdvu27',
   'walletid' => '129328d18339990c7398e02975c4513754881337' 
));
print_r($invoice);
```

### Verify a payment
```php
// Define your URL to API
define('ADAPAY_API', 'http://<IP4-SERVER-ADDRESS>:8090/v2');

/*
 * Check you invoice was paid and you got the payment to your cardano wallet
 * You can simply pass the return parameters you got in your $invoice
 * 
 */
$verify = ADAPAY::verifyPayment(array(
   'hash' => $invoice['hash'],
   'walletid' => $invoice['walletid'],
   'ada' => $invoice['ada'],
   'created' => $invoice['created'],
   'death' => $invoice['death']
));
print_r($verify);

//Or simply pass the $invoice you created
$verify = ADAPAY::verifyPayment($invoice);
print_r($verify);


if($verify['waiting']) {
    //Still waiting for the payment, not received
    
} 
elseif($verify['expired']) {
    //Invoice is expired
    
}
elseif($verify['paid']) {
     /*
     * Payment received
     * Do your database updates, flag invoice as paid etc ...
     */
    
}
```

### Get current ADA rates by EUR, USD, GBP ...
```php
# Get last cardano fiat price, via a pair like: ADAEUR, ADAUSD, ADAGBP ...
$lastPrice = ADAPAY::lastPrice(array('pair' => 'ADAEUR'));
print_r($lastPrice);

$fiat = 10;
$ada = $fiat / $lastPrice;
echo $fiat.' EUR are currently '.$ada.' ADA';

# Get last price in EURO
$lastPriceEuro = ADAPAY::lastPriceEuro();
print_r($lastPriceEuro);

# Get last price in Dollar
$lastPriceDollar = ADAPAY::lastPriceDollar();
print_r($lastPriceDollar);

# Get last price in British Pound
$lastPricePound = ADAPAY::lastPricePound();
print_r($lastPricePound);
```

### More stuff
```php

//Inspect a single cardano address
$inspect = ADAPAY::addressInspect(array('id' => 'addr1qxksn95zhgje7tvdsgfpk9t49sssz4fqewt74neh56cnl4ml8zpc3556jh8exfp70a6f3pva7yf4fmfmw52tdh3dh94sqdvu27'));
print_r($inspect);

/*
 * List all sent and receive transaction on your wallet
 * 
 * id       = Set one of your wallet ids
 * start    = Optional, set a start date in ISO8601
 * end      = Optional, set an end date in ISO8601
 */
$transactions = ADAPAY::walletTransactions(array(
    'id' => '129328d18339990c7398e02975c4513754881337',

    //List all transaction for walletid in the last hour
    'start' => date(DATE_ISO8601, time()-3600)
));
print_r($transactions);
```

## Best practice and recommendations

* For best privacy, run your own cardano node and never ever give out your recovery phrase to any third party server!


* You can restore the same wallet you created on your server on [Deadalus Wallet](https://daedaluswallet.io/). If you have your wallet in Deadalus you can see incoming transaction and managing your wallet will be easier, e.g if you want to send some refunds to your customer or see more details.


* Stake and delegate your earned ADA to our Ticker: **GEEK** and receive extra money for your staking! If you delegated to our **GEEK** Stake Pool all ongoing received payments will be automatically staked. If your wallet grow your stake amount grow automatically and you earn more money. 
You easily delegate your ADA in Deadalus Wallet. So simple restore your created wallet on Deadalus too as we mentioned above.


* Create a second wallet directly in Deadalus Wallet and send some payments to some address in your created ADAPay wallet to test your setup.


* You can use a Javascript setInterval function with an ajax request that check ADAPAY::verifyPayment(); Keep in mind that you should flag your created invoice with status "paid" in a database and only use ADAPAY::verifyPayment(); if you did not yet flag the entry as "paid".
```javascript
<script>
var invoiceStatus = setInterval(function(){
    //need jQuery, you can set any params you want to pass
    $.post("/path/to/your/ajax.php?hash=<?=$invoice['hash];?>&param2=userid&id=dbid&param3=anything", {}, function(data){

        console.info('invoice status: '+ data);
        //if data empty valid invoice but not expired or paid

        if(data == 'paid') {
            //Do some stuff, eg hide some loader indicator 
        }
        if(data == 'waiting') {
            //Do some stuff
        }
        if(data == 'expired') {
            //Do some stuff, eg show messages invoice expired
        }
    });
}, 3000);
</script>
```

```php
/*
 * This is some basic example on your ajax.php
 * Of course you can do your checks however you want
 */

//Highly recommend to clean your parameter requests, ADAPay will help
include('path/to/my/adapay.php');
ADAPAY::cleanRequest();

//Now you should be save to use the parameters from your request
print_r($_REQUEST);

$hash = $_REQUEST['hash'];
$id = $_REQUEST['id'];
$param2 = $_REQUEST['param2'];
$param3 = $_REQUEST['param3'];

//First get your created invoice from your database maybe from your set parameters in your Javascript Ajax request
//TODO

//Check invoice from DB
if(!empty($invoice)) {
    if($invoice['paid']) {
        echo 'paid';
    } else {
        $status = ADAPAY::verifyPayment();
        
        if($status['waiting']) {
            echo 'waiting';
        }
        elseif($status['expired']) {
            echo 'expired';
        }
        else($status['paid']) {
            echo 'paid';
            
            //Highly recommend to update your created invoice in
            //TODO
 
        }
    }
}
```

## 🙏 Supporters

Stake your ADA to our pool with ticker: GEEK  
[GeekMe Stake Pool](https://adapools.org/pool/c13debc5c24d045cf5e2d69c33ff981602ae55d8bded995a6d930836)  

☕ Wanna buy me a coffee or two? Send some ADA to our donation address: 
addr1qxksn95zhgje7tvdsgfpk9t49sssz4fqewt74neh56cnl4ml8zpc3556jh8exfp70a6f3pva7yf4fmfmw52tdh3dh94sqdvu27
