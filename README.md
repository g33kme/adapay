# ADAPay
ADAPay is a free and open-source Cardano (ADA) payment processor script written in PHP. 
You need to run a [cardano-wallet](https://github.com/input-output-hk/cardano-wallet) to work with ADAPay.  

With ADAPay you can simply create invoices for cardano payments in your webshop as well as verify the incoming payments.

Help us improve this project by staking your ADA to our pool with ticker: **GEEK**  
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
wget https://github.com/g33kme/adapay/main/nginx.adapay

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

## Requirements

This library requires PHP 7.1 or later.

## How to use ADApay
With the ADAPay PHP library it's very easy to work with your cardano-wallet API. Check your wallet, craete invoices, check receiving payments ...

### Create or Restore Cardano Wallet

```php
/*
 * First we need to create or restore wallet on our cardano-wallet
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
    'mnemonic' => 'that are just twenty four words that have not meaning and only for adapay as placeholder so dont try to copy this cheers adapay'
    'passphrase' => 'myAwesomeAdaPayPassword',
));
print_r($wallet)

/*
 * If you already created a wallet you can simply show all created wallets and grab your walletid
 */
$wallets = ADAPAY::wallets(array();
print_r($wallets)
```

### Create an invoice
```php
/*
 * How to create an invoice
 * 
 * pair     = set a pair like ADAUSD, ADAEUR, ADAGBP
 * amount   = fiat amount, based on the pair your ada amount for the invoice will be calculated
 * identify = how to identify the paid invoice
 * death    = in seconds when the invoice expires
 * address  = set one of your cardano addresses where you want to receive the payment
 * walletid = set a walletid from your cardano-wallet you created in the first step
 */
$invoice = ADAPAY::invoiceCreate(array(
   'pair' => 'ADAUSD',
   'amount' => 10,
   'identify' => array(
        'type' => 'amount'
   ), 
   'death' => 1800,
   'address' => '',
   'walletid' => '' 
));
print_r($invoice)

/*
 * Each created cardano invoice return an unique hash
 * We recommend to save your invoice in a database
 */

```

### Verify a payment
```php
/*
 * Check you invoice was paid and you got the payment to your cardano wallet
 * You can simply pass the return parameters you got from your invoice
 * 
 */
$verify = ADAPAY::verifyPayment(array(
   'hash' => $invoice['hash'],
   'walletid' => $invoice['walletid'],
   'ada' => $invoice['ada'],
   'created' => $invoice['created'],
   'death' => $invoice['death']
));
print_r($verify)


if($verify['waiting']) {
    //Still waiting for the payment, not received
    
} else {
    
    /*
     * Payment received
     * 
     * Do your database updates, flag invoice as paid etc ...
     */
}
```


## 🙏 Supporters

Stake your ADA to our pool with ticker: GEEK  
[GeekMe Stake Pool](https://adapools.org/pool/c13debc5c24d045cf5e2d69c33ff981602ae55d8bded995a6d930836)
