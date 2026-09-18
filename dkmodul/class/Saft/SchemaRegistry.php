<?php

class DkSaftSchemaRegistry
{
    public const VERSION = '2.1';
    public const NAMESPACE_URI = 'urn:StandardAuditFile-Taxation-Financial:DK';
    public const STANDARD_ACCOUNT_NAME = 'Standardkontoplanen';
    public const STANDARD_ACCOUNT_VERSION = '20260101';
    public const STANDARD_VAT_VERSION = '20260101';

    public const UPSTREAM_REPOSITORY = 'https://git.erst.dk/standard-filformater/standard-filformater.git';
    public const UPSTREAM_COMMIT = 'ea9a4b5704c7a0e9646b0d3b928a59089d71cf0e';
    public const XSD_RELATIVE_PATH = 'SAF-T/XSD/Danish_SAF-T_Financial_Schema_v_2_1.xsd';
    public const EXAMPLE_RELATIVE_PATH = 'SAF-T/XML_examples/SAF-T v. 2.1.xml';

    public static function manifest()
    {
        return array(
            'version' => self::VERSION,
            'namespace' => self::NAMESPACE_URI,
            'upstreamRepository' => self::UPSTREAM_REPOSITORY,
            'upstreamCommit' => self::UPSTREAM_COMMIT,
            'xsdPath' => self::XSD_RELATIVE_PATH,
            'examplePath' => self::EXAMPLE_RELATIVE_PATH,
            'standardAccountName' => self::STANDARD_ACCOUNT_NAME,
            'standardAccountVersion' => self::STANDARD_ACCOUNT_VERSION,
            'standardVatVersion' => self::STANDARD_VAT_VERSION,
        );
    }
}
