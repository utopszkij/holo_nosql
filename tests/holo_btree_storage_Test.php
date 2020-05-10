<?php
declare(strict_types=1);
include_once './catdb.php';
include_once './btree.php';
include_once './holo_btree_storage.php';
use PHPUnit\Framework\TestCase;

global $storage, $rootAddress, $address1, $address2, $address3;

define('HOLOPSW','123456');
define('HOLOURL','http://127.0.0.1:8888');
define('HOLOINSTANCE','test-instance');

// test Cases
class HoloBtreeTest extends TestCase {
    
    public function test_start() {
        global $storage, $rootAddress;
        $storage = new HoloBtreeStorage('db.colName');
        $this->AssertEquals('',$storage->getErrorMsg());
        $rootAddress = $storage->getRoot('123456');
        $storage->rootId = $rootAddress;
    } 
    
    public function test_set_psw() {
        global $storage, $rootAddress;
        $res = $storage->get($rootAddress);
        if ($res->id == "") {
            $res = $storage->setPsw('123456');
            $this->AssertTrue($res);
        }
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_get_root() {
        global $storage, $rootAddress;
        $rootAddress = $storage->getRoot('123456');
        $this->AssertNotEquals('',JSON_encode($rootAddress));
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_get_notfound() {
        global $storage, $rootAddress;
        $res = $storage->get('njhjehiuhc');
        $this->AssertTrue($res->deleted);
        $this->AssertEquals('not_found',$storage->getErrorMsg());
    }
    
    public function test_get_ok() {
        global $storage, $rootAddress;
        $res = $storage->get($rootAddress);
        $this->AssertTrue($res->deleted);
        $this->AssertEquals($rootAddress,$res->id);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_add1() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new BtreeItem();
        $item->key="1234567890";
        $item->value="v1";
        $item->parent = $rootAddress;
        $address1 = $storage->add($item);
        $this->AssertNotEquals('',$address1);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_add2() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new BtreeItem();
        $item->key="1234567890";
        $item->value="a1";
        $item->parent = $rootAddress;
        $address2 = $storage->add($item);
        $this->AssertNotEquals('',$address2);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_add3() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new BtreeItem();
        $item->key="1234567890";
        $item->value="xy";
        $item->parent = $rootAddress;
        $address3 = $storage->add($item);
        $this->AssertNotEquals('',$address3);
        $this->AssertEquals('',$storage->getErrorMsg());
    }
    
    public function test_delete_ok() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address3);
        $res = $storage->delete($item);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertTrue($res);
        
        $res = $storage->get($address3);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertTrue($res->deleted);
    }
    
    
    public function test_delete_not_found() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = new BtreeItem;
        $item->id = "jhjhiuhh";
        $res = $storage->delete($item);
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_delete_alredy_deleted() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address3);
        $res = $storage->delete($item);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
        
        $res = $storage->get($address3);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertTrue($res->deleted);
    }
    
    public function test_set_left_ok() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $res = $storage->setLeft($item, $address2 );
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertTrue($res);
        
        $res = $storage->get($address1);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertEquals($address2, $res->left);
    }
    
    public function test_set_left_item_notexists() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $item->id = "asasasas";
        $res = $storage->setLeft($item, $address2 );
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_set_left_target_invalid() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $res = $storage->setLeft($item, "asaddfrfg" );
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_set_left_exists() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $res = $storage->setLeft($item, $address2 );
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_set_right_ok() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $res = $storage->setRight($item, $address2 );
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertTrue($res);
        
        $res = $storage->get($address1);
        $this->AssertEquals('', $storage->getErrormsg());
        $this->AssertEquals($address2, $res->right);
    }
    
    public function test_set_right_item_notexists() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $item->id = "asasasas";
        $res = $storage->setRight($item, $address2 );
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_set_right_target_invalid() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $res = $storage->setRight($item, "asaddfrfg" );
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
    public function test_set_right_exists() {
        global $storage, $rootAddress, $address1, $address2, $address3;
        $item = $storage->get($address1);
        $res = $storage->setRight($item, $address2 );
        $this->AssertNotEquals('', $storage->getErrormsg());
        $this->AssertFalse($res);
    }
    
 
}

?>