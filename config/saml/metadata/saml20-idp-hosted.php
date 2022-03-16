<?php
/**
 * SAML 2.0 IdP configuration for simpleSAMLphp.
 *
 * See: https://rnd.feide.no/content/idp-hosted-metadata-reference
 */

$baseurlpath = include dirname(__DIR__) . '/config/baseurl.php';
$host = parse_url($baseurlpath, PHP_URL_SCHEME+PHP_URL_HOST+PHP_URL_PORT);

/*
    Test idp
*/
$metadata[$baseurlpath.'saml2/idp/metadata.php'] = array(
    'host' => '__DEFAULT__',
    'auth' => 'localtest',
    'privatekey' => 'umvc.pem',
    'certificate' => 'umvc.crt',
    'signature.algorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    'AttributeNameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    'userid.attribute' => 'uid',
    'OrganizationName' => array(
        'hu' => 'Helyi teszt felhasználók',
        'en' => 'Local Test Authentication Source'
    ),
    'OrganizationURL' => $host,
    'authproc' => array(
        13 => array(
            'class' => 'core:AttributeAdd',
            'schacHomeOrganizationType' => array('urn:schac:homeOrganizationType:hu:university')
        ),
        15 => array(
            'class' => 'core:ScopeAttribute',
            'scopeAttribute' => 'schacHomeOrganization',
            'sourceAttribute' => 'uid',
            'targetAttribute' => 'eduPersonPrincipalName',
            'onlyIfEmpty' => true,
        ),
        30 => array(
            'class' => 'core:ScopeAttribute',
            'scopeAttribute' => 'schacHomeOrganization',
            'sourceAttribute' => 'eduPersonAffiliation',
            'targetAttribute' => 'eduPersonScopedAffiliation',
        ),

        // eduPersonTargetedID előállítása az userid.attribute alapján
        40 => array(
            'class' => 'core:TargetedID',
            'nameId' => TRUE,	// SAML2 NameID formátum
        ),
    ),
    'attributeencodings' => array(
        'urn:oid:1.3.6.1.4.1.5923.1.1.1.10' => 'raw',
    ),
    'redirect.sign' => true,
    'PrivacyStatementURL' => $host,
);
