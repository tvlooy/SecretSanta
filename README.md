# Welcome to Secret Santa Online gift exchange organizer!

[![Build Status](https://travis-ci.org/Intracto/SecretSanta.svg?branch=master)](https://travis-ci.org/Intracto/SecretSanta)

Secret Santa Organizer is a free online Secret Santa gift exchange organizer! Organize a Secret Santa party with friends,
family or even co-workers and add your wishlist.

See [LICENSE](https://github.com/Intracto/SecretSanta/blob/master/LICENSE) for usage terms.

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/5e845a60-cf8f-4e83-97d3-ecacb19cd091/big.png)](https://insight.sensiolabs.com/projects/5e845a60-cf8f-4e83-97d3-ecacb19cd091)

## Getting started

First get the code on your machine.

```
git clone git@github.com:Intracto/SecretSanta.git
cd SecretSanta
```

The setup is only tested with these versions. If you see failures use the exact versions:

  - VirtualBox 5.1.22
  - Vagrant 1.8.7
  - Ansible 2.3.1.0

```
vagrant up
```

Add these records to your own ```/etc/hosts``` file:

```
192.168.33.50 dev.secretsantaorganizer.com
192.168.33.50 mails.dev.secretsantaorganizer.com
192.168.33.50 phpmyadmin.dev.secretsantaorganizer.com
```

Browse to http://dev.secretsantaorganizer.com/app_dev.php to see the project homepage.

## Extra info

If you need root in the box, use ```sudo -i``` or password ```vagrant```.

All mails on the system are intercepted, regardless sender and receiver, and are delivered locally. You can access
these mails from the URL ```mails.dev.secretsantaorganizer.com```.

There is access to the MySQL database from URL ```phpmyadmin.dev.secretsantaorganizer.com```, or with a remote connection.
Login with user ```secretsanta```, password ```vagrant```.

Xdebug remote debugging is enabled. Configure your PhpStorm so you can step debug the code.

Run the tests with:

```
phpunit.phar -c app
```

[Writing and running Behat tests is documented here.](https://github.com/Intracto/SecretSanta/blob/master/docs/behat.md)

Building the frontend:

```
cd /vagrant/src/Intracto/SecretSantaBundle/Resources/public
gulp build
```
