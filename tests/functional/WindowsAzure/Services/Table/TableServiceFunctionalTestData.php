<?php

/**
 * Functional tests for the SDK
 *
 * PHP version 5
 *
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   Microsoft
 * @package    Tests\Functional\WindowsAzure\Services\Table
 * @author     Jason Cooke <jcooke@microsoft.com>
 * @copyright  2012 Microsoft Corporation
 * @license    http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link       http://pear.php.net/package/azure-sdk-for-php
 */

namespace Tests\Functional\WindowsAzure\Services\Table;

use WindowsAzure\Utilities;
use WindowsAzure\Core\WindowsAzureUtilities;
use WindowsAzure\Services\Core\Models\Logging;
use WindowsAzure\Services\Core\Models\Metrics;
use WindowsAzure\Services\Core\Models\RetentionPolicy;
use WindowsAzure\Services\Core\Models\ServiceProperties;
use WindowsAzure\Services\Table\Models\EdmType;
use WindowsAzure\Services\Table\Models\Entity;
use WindowsAzure\Services\Table\Models\Property;
use WindowsAzure\Services\Table\Models\Query;
use WindowsAzure\Services\Table\Models\QueryTablesOptions;
use WindowsAzure\Services\Table\Models\TableServiceOptions;
use WindowsAzure\Services\Table\Models\Filters\BinaryFilter;
use WindowsAzure\Services\Table\Models\Filters\ConstantFilter;
use WindowsAzure\Services\Table\Models\Filters\Filter;
use WindowsAzure\Services\Table\Models\Filters\PropertyNameFilter;
use WindowsAzure\Services\Table\Models\Filters\UnaryFilter;

class FakeTableInfoEntry {
    public $TableName;
}

class FakeTEntityEntry {
    private $properties = array();

    public function getPartitionKey() {
        return $this->getProperty('PartitionKey');
    }

    public function setPartitionKey($partitionKey) {
        $this->addProperty('PartitionKey', null, $partitionKey);
        return this;
    }

    public function getRowKey() {
        return getPropertyValue('RowKey');
    }

    public function setRowKey($rowKey) {
        $this->addProperty('RowKey', null, $rowKey);
        return this;
    }

    public function getProperties() {
        return $this->properties;
    }

    public function getProperty($name) {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        } else  {
            return null;
        }
    }

    public function setProperty($name, $property) {
        $this->properties[$name] = $property;
        return this;
    }

    public function newProperty($name, $edmType, $value) {
        $property = new Property();
        $property->setEdmType($edmType);
        $property->setValue($value);
        $this->addProperty($name, $property);
        return this;
    }

    public function getPropertyValue($name) {
        $p = $this->getProperty($name);
        return is_null($p) ? null : $p->getValue();
    }
}

class TableServiceFunctionalTestData {
    private static $tempTableCounter;
    private static $nonExistTablePrefix;
    public static $testUniqueId;
    public static $TEST_TABLE_NAMES;

    const IntegerMAX_VALUE = 2147483647;
    public static $IntegerMIN_VALUE;
    const LongBigValue = 1234567890;
    const LongBigNegativeValue = -123456789032;

    public function __construct(){
        self:: $setupData;
    }

    public static function setupData() {
        self::$IntegerMIN_VALUE = -1 - self::IntegerMAX_VALUE;
        $rint = rand(0,1000000);
        self::$testUniqueId = 'qaX' . $rint . 'X';
        self::$nonExistTablePrefix = 'qaX' . ($rint + 1) . 'X';
        self::$TEST_TABLE_NAMES = array( self::$testUniqueId . 'a1', self::$testUniqueId . 'a2', self::$testUniqueId . 'b1' );
    }

    static function getInterestingTableName() {
        return self::$testUniqueId . 'int' . (self::$tempTableCounter++);
    }

    static function getNewKey() {
        return self::$testUniqueId . 'key' . (self::$tempTableCounter++);
    }

    static function getUnicodeString() {
        return  chr(0xEB) . chr(0x8B) . chr(0xA4) . // \uB2E4 in UTF-8
                chr(0xEB) . chr(0xA5) . chr(0xB4) . // \uB974 in UTF-8
                chr(0xEB) . chr(0x8B) . chr(0xA4) . // \uB2E4 in UTF-8
                chr(0xEB) . chr(0x8A) . chr(0x94) . // \uB294 in UTF-8
                chr(0xD8) . chr(0xA5) .             // \u0625 in UTF-8
                ' ' . 
                chr(0xD9) . chr(0x8A) .             // \u064A in UTF-8
                chr(0xD8) . chr(0xAF) .             // \u062F in UTF-8
                chr(0xD9) . chr(0x8A) .             // \u064A in UTF-8
                chr(0xD9) . chr(0x88);              // \u0648 in UTF-8
    }
    
    public static function getDefaultServiceProperties() {
        // This is the default that comes from the server.
        $rp = new RetentionPolicy();
        $l = new Logging();
        $l->setRetentionPolicy($rp);
        $l->setVersion('1.0');
        $l->setDelete(false);
        $l->setRead(true);
        $l->setWrite(false);

        $m = new Metrics();
        $m->setRetentionPolicy($rp);
        $m->setVersion('1.0');
        $m->setEnabled(false);
        $m->setIncludeAPIs(null);

        $sp = new ServiceProperties();
        $sp->setLogging($l);
        $sp->setMetrics($m);

        return $sp;
    }

    public static function getInterestingServiceProperties() {
        $ret = array();

        // This is the default that comes from the server.
        array_push($ret, self::getDefaultServiceProperties());

        {
            $rp = new RetentionPolicy();
            $rp->setEnabled(true);
            $rp->setDays(10);

            $l = new Logging();
            $l->setRetentionPolicy($rp);
            // Note: looks like only v1.0 is available now.
            // http://msdn.microsoft.com/en-us/library/windowsazure/hh360996.aspx
            $l->setVersion('1.0');
            $l->setDelete(true);
            $l->setRead(true);
            $l->setWrite(true);

            $m = new Metrics();
            $m->setRetentionPolicy($rp);
            $m->setVersion('1.0');
            $m->setEnabled(true);
            $m->setIncludeAPIs(true);

            $sp = new ServiceProperties();
            $sp->setLogging($l);
            $sp->setMetrics($m);

            array_push($ret, $sp);
        }

        {
            $rp = new RetentionPolicy();
            $rp->setEnabled(false);
            $rp->setDays(null);

            $l = new Logging();
            $l->setRetentionPolicy($rp);
            // Note: looks like only v1.0 is available now.
            // http://msdn.microsoft.com/en-us/library/windowsazure/hh360996.aspx
            $l->setVersion('1.0');
            $l->setDelete(false);
            $l->setRead(false);
            $l->setWrite(false);

            $m = new Metrics();
            $m->setRetentionPolicy($rp);
            $m->setVersion('1.0');
            $m->setEnabled(true);
            $m->setIncludeAPIs(true);

            $sp = new ServiceProperties();
            $sp->setLogging($l);
            $sp->setMetrics($m);

            array_push($ret, $sp);
        }

        {
            $rp = new RetentionPolicy();
            $rp->setEnabled(true);
            // Days has to be 0 < days <= 365
            $rp->setDays(364);

            $l = new Logging();
            $l->setRetentionPolicy($rp);
            // Note: looks like only v1.0 is available now.
            // http://msdn.microsoft.com/en-us/library/windowsazure/hh360996.aspx
            $l->setVersion('1.0');
            $l->setDelete(false);
            $l->setRead(false);
            $l->setWrite(false);

            $m = new Metrics();
            $m->setVersion('1.0');
            $m->setEnabled(false);
            $m->setIncludeAPIs(null);
            $m->setRetentionPolicy($rp);

            $sp = new ServiceProperties();
            $sp->setLogging($l);
            $sp->setMetrics($m);

            array_push($ret, $sp);
        }

        return $ret;
    }

    static function getInterestingQueryTablesOptions() {
        $ret = array();


        $options = new QueryTablesOptions();
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $options->setTop(2);
        $options->setPrefix(self::$nonExistTablePrefix);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $options->setTop(-2);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyEq(Filter::applyConstant(self::$TEST_TABLE_NAMES[1]), Filter::applyPropertyName('TableName'));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyEq(Filter::applyConstant(self::$TEST_TABLE_NAMES[2]), Filter::applyPropertyName('TableName'));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyAnd(
                Filter::applyEq(Filter::applyConstant(self::$TEST_TABLE_NAMES[1]), Filter::applyPropertyName('TableName')),
                Filter::applyEq(Filter::applyConstant(self::$TEST_TABLE_NAMES[2]), Filter::applyPropertyName('TableName')));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyAnd(
                Filter::applyGe(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[1])), 
                Filter::applyLe(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[2])));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyOr(
                Filter::applyGe(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[1])), 
                Filter::applyGe(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[2])));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyAnd(
                Filter::applyEq(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[1])), 
                Filter::applyGe(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[0])));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyOr(
                Filter::applyEq(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[1])), 
                Filter::applyGe(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[2])));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyOr(
                Filter::applyEq(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[1])), 
                Filter::applyEq(Filter::applyPropertyName('TableName'), Filter::applyConstant(self::$TEST_TABLE_NAMES[2])));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $filter = Filter::applyOr(
                Filter::applyEq(Filter::applyConstant(self::$TEST_TABLE_NAMES[1]), Filter::applyPropertyName('TableName')), 
                Filter::applyEq(Filter::applyConstant(self::$TEST_TABLE_NAMES[2]), Filter::applyPropertyName('TableName')));
        $options->setFilter($filter);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $options->setPrefix(self::$nonExistTablePrefix);
        array_push($ret, $options);

        if (!WindowsAzureUtilities::isEmulated()) {
            $options = new QueryTablesOptions();
            $options->setPrefix(self::$testUniqueId);
            array_push($ret, $options);
        }

        $options = new QueryTablesOptions();
        $nextTableName = self::$TEST_TABLE_NAMES[1];
        $options->setNextTableName($nextTableName);
        array_push($ret, $options);

        $options = new QueryTablesOptions();
        $nextTableName = self::$nonExistTablePrefix;
        $options->setNextTableName($nextTableName);
        array_push($ret, $options);


        return $ret;
    }

    static function getSimpleinsertEntityOptions() {
        return new TableServiceOptions();
    }

    static function getSimpleEntity() {
        $entity = new Entity();
        $entity->setPartitionKey(self::getNewKey());
        $entity->setRowKey(self::getNewKey());
        return $entity;
    }

    static function getInterestingEntities() {
        $ret = array();

        array_push($ret, self::getSimpleEntity());

        $e = new Entity();
        $e->addProperty('RowKey', EdmType::STRING, self::getNewKey());
        $e->addProperty('PartitionKey', null, self::getNewKey());
        array_push($ret, $e);

        $e = new Entity();
        $e->setPartitionKey(self::getNewKey());
        $e->setRowKey(self::getNewKey());
        $e->addProperty('BINARY', EdmType::BINARY, chr(0) . chr(1) . chr(2) . chr(3) . chr(4));
        $e->addProperty('BOOLEAN', EdmType::BOOLEAN, true);
        $e->addProperty('DATETIME', EdmType::DATETIME, Utilities::convertToDateTime('2012-01-26T18:26:19.0000473Z'));
        $e->addProperty('DOUBLE', EdmType::DOUBLE, 12345678901);
        $e->addProperty('GUID', EdmType::GUID, '90ab64d6-d3f8-49ec-b837-b8b5b6367b74');
        $e->addProperty('INT32', EdmType::INT32, 23);
        $e->addProperty('INT64', EdmType::INT64, '-1');
        $now = new \DateTime();
        $e->addProperty('STRING', EdmType::STRING, $now->format(\DateTime::COOKIE));
        array_push($ret, $e);

        $e = new Entity();
        $e->setPartitionKey(self::getNewKey());
        $e->setRowKey(self::getNewKey());
        $e->addProperty('test', EdmType::BOOLEAN, true);
        $e->addProperty('test2', EdmType::STRING, 'value');
        $e->addProperty('test3', EdmType::INT32, 3);
        $e->addProperty('test4', EdmType::INT64, '12345678901');
        $e->addProperty('test5', EdmType::DATETIME, new \DateTime());
        array_push($ret, $e);

        $e = new Entity();
        $e->setPartitionKey(self::getNewKey());+
        
        
        $e->setRowKey(self::getNewKey());
        $e->addProperty('BINARY', EdmType::BINARY, null);
        $e->addProperty('BOOLEAN', EdmType::BOOLEAN, null);
        $e->addProperty('DATETIME', EdmType::DATETIME, null);
        $e->addProperty('DOUBLE', EdmType::DOUBLE, null);
        $e->addProperty('GUID', EdmType::GUID, null);
        $e->addProperty('INT32', EdmType::INT32, null);
        $e->addProperty('INT64', EdmType::INT64, null);
        $e->addProperty('STRING', EdmType::STRING, null);
        array_push($ret, $e);

        return $ret;
    }

    static function getInterestingBadEntities() {
        $ret = array();

        $e = new Entity();
        array_push($ret, $e);

        $e = new Entity();
        $e->setRowKey(self::getNewKey());
        array_push($ret, $e);

        $e = new Entity();
        $e->setPartitionKey(self::getNewKey());
        array_push($ret, $e);
        
        return $ret;
    }

    static function getSimpleEntities($count) {
        $ret = array();

        $e = new Entity();
        $e->setPartitionKey('singlePartition');
        $e->setRowKey(self::getNewKey());
        $e->addProperty('INT32', EdmType::INT32, 23);
        array_push($ret, $e);

        $booleans = self::getInterestingGoodBooleans();
        $dates = self::getInterestingGoodDates();
        $doubles = self::getInterestingGoodDoubles();
        $guids = self::getInterestingGoodGuids();
        $ints = self::getInterestingGoodInts();
        $longs = self::getInterestingGoodLongs();
        $binaries = self::getInterestingGoodBinaries();
        $strings = self::getInterestingGoodStrings();

        // The random here is not to generate random values, but to
        // get a good mix of values in the table entities.
        mt_srand(123);
        for ($i = 0; $i < $count - 1; $i++) {
            $e = new Entity();
            $e->setPartitionKey('singlePartition');
            $e->setRowKey(self::getNewKey());
            self::addProperty($e, 'BINARY', EdmType::BINARY, $binaries);
            self::addProperty($e, 'BOOLEAN', EdmType::BOOLEAN, $booleans);
            self::addProperty($e, 'DATETIME', EdmType::DATETIME, $dates);
            self::addProperty($e, 'DOUBLE', EdmType::DOUBLE, $doubles);
            self::addProperty($e, 'GUID', EdmType::GUID, $guids);
            self::addProperty($e, 'INT32', EdmType::INT32, $ints);
            self::addProperty($e, 'INT64', EdmType::INT64, $longs);
            self::addProperty($e, 'STRING', EdmType::STRING, $strings);
            array_push($ret, $e);
        }

        return $ret;
    }

    static function addProperty($e, $name, $edmType, $binaries) {
        $index = mt_rand(0, count($binaries));
        if ($index < count($binaries)) {
            $e->addProperty($name, $edmType, $binaries[$index]);
        }
    }

    static function getInterestingGoodBooleans() {
        $ret = array();
        array_push($ret, true);
        array_push($ret, false);
//        array_push($ret, 'TRUE');
//        array_push($ret, 1);
        return $ret;
    }

    static function getInterestingBadBooleans() {
        $ret = array();
        array_push($ret, 'BOO!');
        return $ret;
    }

    static function getInterestingGoodDates() {
        $ret = array();

        array_push($ret, new \DateTime());

        $c = new \DateTime;
        $c->setDate(2010, 2, 3);
        $c->setTime(20, 3, 4);
        array_push($ret, $c);

        $c = new \DateTime;
        $c->setDate(2012, 1, 27);
        $c->setTime(21, 46, 59);
        array_push($ret, $c);

        $c = new \DateTime('27 Jan 2012 22:00:00.800 GMT');
        array_push($ret, $c);

        return $ret;
    }

    static function getInterestingBadDates() {
        $ret = array();
        array_push($ret, true);
        array_push($ret, 0);
        return $ret;
    }

    static function getInterestingGoodDoubles() {
        $ret = array();
        array_push($ret, pi());
        array_push($ret, 0.0);
        array_push($ret, floatval(self::IntegerMAX_VALUE));
        array_push($ret, floatval(self::LongBigValue));
        array_push($ret, 2.3456);
        array_push($ret, 1.0e-10);
        return $ret;
    }

    static function getInterestingBadDoubles() {
        $ret = array();
        array_push($ret, 'ABCDEFGH-D3F8-49EC-B837-B8B5B6367B74');
        return $ret;
    }

    static function getInterestingGoodGuids() {
        $ret = array();
        array_push($ret, '90ab64d6-d3f8-49ec-b837-b8b5b6367b74');
        array_push($ret, '00000000-0000-0000-0000-000000000000');
        return $ret;
    }

    static function getInterestingBadGuids() {
        $ret = array();
        array_push($ret, 'ABCDEFGH-D3F8-49EC-B837-B8B5B6367B74');
        array_push($ret, '');
        return $ret;
    }

    static function getInterestingGoodInts() {
        $ret = array();
        array_push($ret, 0);
        array_push($ret, self::IntegerMAX_VALUE);
        array_push($ret, self::$IntegerMIN_VALUE);
        array_push($ret, 35536);
        return $ret;
    }

    static function getInterestingBadInts() {
        $ret = array();
        array_push($ret, false);
        array_push($ret, self::IntegerMAX_VALUE + 1);
        return $ret;
    }
    
    static function getInterestingGoodLongs() {
        $ret = array();
        array_push($ret, '0');
        array_push($ret, strval(self::LongBigValue));
        array_push($ret, strval(self::LongBigNegativeValue));
        array_push($ret, '35536');
        return $ret;
    }

    static function getInterestingBadLongs() {
        $ret = array();
        array_push($ret, false);
        array_push($ret, '9223372036854775808');
        return $ret;
    }

    static function getInterestingGoodBinaries() {
        $ret = array();
        array_push($ret, '');
        array_push($ret, chr(1) . chr(2) . chr(3) . chr(4) . chr(5));
        array_push($ret, chr(255) . chr(254) . chr(253));
        return $ret;
    }

    static function getInterestingBadBinaries() {
        $ret = array();
        array_push($ret, 12345);
        array_push($ret, new \DateTime());
        return $ret;
    }

    static function getInterestingGoodStrings() {
        $ret = array();
        array_push($ret, 'AQIDBAU='); // Base-64 encoded byte array { 0x01, 0x02, 0x03, 0x04, 0x05 };
        array_push($ret, 'false');
        array_push($ret, '12345');
        array_push($ret, '\\' . '\\' . '\'' . '(?++\\.&==/&?\'\'$@://   .ne');
        array_push($ret, '12345');
        array_push($ret, 'Some unicode: ' . self::getUnicodeString());
        array_push($ret, strval(self::IntegerMAX_VALUE));
        array_push($ret, '<some><XML></stuff>');
        return $ret;
    }

    static function getInterestingBadStrings() {
        $ret = array();
        // Are there any?
        return $ret;
    }
}

?>
