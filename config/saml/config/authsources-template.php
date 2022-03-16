<?php

$app_config = include dirname(dirname(__DIR__)).'/config.php';
$idp = $app_config['saml_idp'];
$disco = $app_config['saml_disco'];
$localUsersFile = dirname(dirname(__DIR__)).'/test_users.php';
$localusers = file_exists($localUsersFile) ? include $localUsersFile : null;
$baseurlpath = include __DIR__ . '/baseurl.php';
$authSource = $app_config['saml_auth_source'];

$config = array(

	// This is an authentication source which handles admin authentication.
	'admin' => array(
		// The default is to use core:AdminPassword, but it can be replaced with
		// any authentication source.
		'core:AdminPassword',
	),

	'localtest' => array(
		'exampleauth:UserPass',
		'local1:local1' => array(
			'uid' => array('local1'),
			'displayName' => 'Local Test One',
			'schacHomeOrganization' => 'umvc.test',
			'eduPersonAffiliation' => array('member', 'employee'),
			'eduPersonPrincipalName' => 'local1@umvc.test',
			'mail' => 'local1@umvc.test',
            'o' => 'umvc',
		),
	),

    /**
     * Create the authettication source for your application using this template
     */
	$authSource => array(
		'saml:SP',

		// The entity ID of this SP.
		// Can be NULL/unset, in which case an entity ID is generated based on the metadata URL.
		'entityID' => null,

		// The entity ID of the IdP this should SP should contact.
		// Can be NULL/unset, in which case the user will be shown a list of available IdPs.
		'idp' => $idp ?: null,
		'disco' => $disco ?: null,

		'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
		'authproc' => array(
			91 => array('class' => 'core:AttributeMap', 'oid2name'),
		),
		'name' => '',
		'attributes' => [
			'eduPersonPrincipalName' => '1.3.6.1.4.1.5923.1.1.1.6',
			'displayName' => '2.16.840.1.113730.3.1.241',
			'mail' => '0.9.2342.19200300.100.1.3',
			'eduPersonAffiliation' => '1.3.6.1.4.1.5923.1.1.1.1',
		],
		'attributes.required' => [
			'eduPersonPrincipalName' => '1.3.6.1.4.1.5923.1.1.1.6',
			'displayName' => '2.16.840.1.113730.3.1.241',
			'mail' => '0.9.2342.19200300.100.1.3',
			'eduPersonAffiliation' => '1.3.6.1.4.1.5923.1.1.1.1',
		],
		'OrganizationName' => 'UMVC',
		'OrganizationURL' => 'https://umvc.test',
		'contacts' => [
			[
				'contactType'       => 'support',
				'emailAddress'      => 'support@example.org',
				'givenName'         => 'John',
				'surName'           => 'Doe',
				'telephoneNumber'   => '+31(0)12345678',
				'company'           => 'UMVC',
			]
		],
		'description' => [
			'en' => '',
		],
		'UIInfo' => [
			'DisplayName' => [
				'en' => '',
			],
			'Description' => [
				'en' => '',
			],
			'InformationURL' => [
				'en' => '',
			],
			'PrivacyStatementURL' => [
                'en' => '',
			],
			'Keywords' => [
			],
			'Logo' => [
			],
		]
	),
);

if($localusers && is_array($localusers)) {
	$config['localtest'] = array_merge($config['localtest'], $localusers);
}
