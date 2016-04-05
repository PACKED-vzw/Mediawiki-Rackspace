# Mediawiki-Rackspace
Extension to support Rackspace cloud files as a back-end for Mediawiki file storage. It subclasses the internal `SwiftFileBackend` class and updates the autentication function to use the new (V2.0) Rackspace authentication API.

## Installation
Download the `RackspaceFileBackend` folder to your `extensions` folder.

Install it by putting the following at the bottom of your `LocalSettings.php` file.
```php
wfLoadExtension('RackspaceFileBackend');
```

## Configuration
Before you can use this extension, you will have to  configure a few things.

You must already have a [Rackspace](https://www.rackspace.com/) account with _Cloud Files_ enabled.

### $wgFileBackends
`$wgFileBackends` is a setting in `LocalSettings.php` that controls to which storage backend Mediawiki tries to write images. To configure it, you will need your Rackspace username, API key and wikiId (a short name of your wiki, without spaces).

Put the following in `LocalSettings.php`:
```php
$wgFileBackends[] = array(
	'name' => "Rackspace",
	'class' => 'RackspaceCloudFiles',
	'wikiId' => '_your_wiki_id_',
	'lockManager' => 'nullLockManager',
	'swiftAuthUrl' => 'https://identity.api.rackspacecloud.com',
	'swiftUser' => '_your_username_',
	'swiftKey' => '_your_api_key_',
	'swiftUseCDN' => true
);
```

### Containers
Mediawiki requires 4 containers to storage images: a _public_, _thumb_, _temp_ and _deleted_ container. You will have to create them beforehand, with your wikiId as a prefix (e.g. `wikiId-containername`).

The containers must be public and must use the Rackspace CDN. You will need the HTTPS (CDN) links for all containers (except the container for _deleted_ items).

* `_CDN_URL_` must be replaced with the CDN URL.
* `CONTAINER_NAME_` contains the name of the container you created (without the prefix).

Update `LocalSettings.php` (below `$wgFileBackends`) with the following:

```php
$wgLocalFileRepo = array (
	'class' => 'LocalRepo',
	'name' => 'rackspace',
	'backend' => 'Rackspace',
	'scriptDirUrl'       => $wgScriptPath,
	'scriptExtension'    => $wgScriptExtension,
	'hashLevels'         => 0,
	'deletedHashLevels'  => 0,
	'zones'              => array(
		// Change container and url values to match your configuration settings
		'public'  =>  array( 'container' =>  '_CONTAINER_NAME_', 'url' => '_CDN_URL_' ),
		'thumb'   =>  array( 'container' =>  '_CONTAINER_NAME_',  'url' => '_CDN_URL_' ),
		'temp'    =>  array( 'container' =>  '_CONTAINER_NAME_',   'url' => '_CDN_URL_' ),
		'deleted' =>  array( 'container' =>  '_CONTAINER_NAME_' ),    // deleted items don't have a URL
	)
);
```