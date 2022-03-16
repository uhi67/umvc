<?php
/**
 * SAML 2.0 remote SP metadata for simpleSAMLphp.
 * See: http://simplesamlphp.org/docs/trunk/simplesamlphp-reference-sp-remote
 */

$baseurlpath = include dirname(__DIR__) . '/config/baseurl.php';
$app_config = include dirname(__DIR__,3).'/config.php';
$authSource = $app_config['saml_auth_source'];

$metadata[$baseurlpath.'module.php/saml/sp/metadata.php/'.$authSource] = array (
	'AssertionConsumerService' => $baseurlpath.'module.php/saml/sp/saml2-acs.php/'.$authSource,
	'SingleLogoutService' => $baseurlpath.'module.php/saml/sp/saml2-logout.php/'.$authSource,
	'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
    'PrivacyStatementURL' => array (
		'hu' => dirname($baseurlpath).'/privacy.html',
    ),
  	'attributes.NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
);
