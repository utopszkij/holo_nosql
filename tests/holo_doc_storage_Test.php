<?php
declare(strict_types=1);
include_once './catdb.php';
include_once './btree.php';
include_once './holo_btree_storage.php';
include_once './holo_doc_storage.php';
use PHPUnit\Framework\TestCase;

global $storage, $rootAddress, $address1, $address2, $address3;

define('HOLOPSW','123456');
define('HOLOURL','http://127.0.0.1:8888');
define('HOLOINSTANCE','test-instance');

// test Cases
class HoloDocTest extends TestCase {
    
    public function test_start() {
        global $storage, $rootAddress;
        $storage = new HoloDocStorage();
        $this->AssertEquals('',$storage->getErrorMsg());
        $rootAddress = $storage->getRoot('123456');
        $storage->rootId = $rootAddress;
        $this->AssertNotEquals('',$rootAddress);
    } 
    
    // this process correct only once where first call in inited DNA!
    public function test_set_psw() {
        global $storage, $rootAddress;
        $res = $storage->setPsw('123456');
        $this->AssertTrue($res);
    }
    
    public function test_get_root() {
        global $storage, $rootAddress;
        $rootAddress = $storage->getRoot('123456');
        $this->AssertNotEquals('',JSON_encode($rootAddress));
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_read_notfound() {
        global $storage, $rootAddress;
        $res = $storage->read('njhjehiuhc');
        $this->AssertEquals('',$res->id);
        $this->AssertEquals('not_found',$storage->getErrorMsg());
    }
    
    public function test_add1() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new Document();
        $item->ty="doc";
        $item->colName="col11";
        $item->f1 = 1;
        $address1 = $storage->add($item);
        $this->AssertNotEquals('',$address1);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_add2() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new Document();
        $item->ty="doc";
        $item->colName="col2";
        $item->f1 = 2;
        $address2 = $storage->add($item);
        $this->AssertNotEquals('',$address2);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_add3() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new Document();
        $item->ty="doc";
        $item->colname="xy";
        $item->f1 = 3;
        $address3 = $storage->add($item);
        $this->AssertNotEquals('',$address3);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_read_ok() {
        global $storage, $rootAddress, $address1;
        $res = $storage->read($address1);
        $this->AssertEquals(1,$res->f1);
        $this->AssertEquals($address1,$res->id);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_remove_ok() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->read($address3);
        $res = $storage->remove($item);
        $this->AssertEquals('', $storage->getErrorMsg());
        $this->AssertTrue($res);
        
        $res = $storage->read($address3);
        $this->AssertEquals('not_found', $storage->getErrorMsg());
        $this->AssertEquals('',$res->id);
    }
    
    
    public function test_remove_not_found() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new Document;
        $item->id = "jhjhiuhh";
        $res = $storage->remove($item);
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_delete_alredy_deleted() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->read($address3);
        $res = $storage->remove($item);
        $this->AssertFalse($res);
    }
    
    public function test_update() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item1 = $storage->read($address1);
        $item2 = $storage->read($address1);
        $item2->id = '';
        $item2->f3 = 3;
        
        $res = $storage->update($item1, $item2);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertTrue($res);
        
        $this->AssertEquals('', $storage->getErrormsg());
        $res = $storage->read($address1);
        $this->AssertEquals(3,$res->f3);
    }
    
    public function test_update_notfound() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item1 = $storage->read($address1);
        $item1->id = 'wwww';
        $item2 = $storage->read($address1);
        $item2->id = '';
        $item2->f3 = 3;
        
        $res = $storage->update($item1, $item2);
        $this->AssertEquals('not_found', $storage->getErrorMsg());
        $this->AssertFalse($res);
        
    }
 
}

?>