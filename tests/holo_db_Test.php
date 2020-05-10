<?php
declare(strict_types=1);

define('DOC_STORAGE_NEME','HoloDocStorage');
define('KEY_STORAGE_NAME','HoloBtreeStorage');
define('KEY_CLASS_NAME','Btree');
define('HOLOPSW','123456');
define('HOLOURL','http://127.0.0.1:8888');
define('HOLOINSTANCE','test-instance');


include_once './btree.php';
include_once './catdb.php';
include_once './holo_btree_storage.php';
include_once './holo_doc_storage.php';
use PHPUnit\Framework\TestCase;

global $db, $col1, $col2, $col3, $docid1; $docid2;
$docStorage = new HoloDocStorage();

// test Cases
class HoloDbTest extends TestCase {
    
    public function test_start() {
		global $docStorage, $db, $col1, $col2, $docid1; $docid2;
		$db = new Db();
      $this->AssertEquals('',$db->getErrorMsg());
    } 
   
    
    // ======================== Db test =====================
    
    public function test_create_collection1() {
        global $docStorage, $db, $col1, $col2, $docid1; $docid2;
        $col1 = $db->createCollection('col1',['id','field1','field2']);
        $this->AssertEquals('',$db->getErrorMsg());
        $this->AssertEquals('col1',$col1->name);
    }
    
     public function test_create_collection_double() {
        global $docStorage, $db, $col1, $col2, $docid1; $docid2;
        $res = $db->createCollection('col1',['id','field1','field2']);
        $this->AssertEquals('col1 collection is exists',$db->getErrorMsg());
        $this->AssertEquals('',$res->name);
    }
    
     public function test_create_collection2() {
        global $docStorage, $db, $col1, $col2, $docid1; $docid2;
        $col2 = $db->createCollection('col2',['id','f1','f2']);
        $this->AssertEquals('',$db->getErrorMsg());
        $this->AssertEquals('col2',$col2->name);
    }
    
    public function test_get_collection1() {
        global $docStorage, $db, $col1, $col2, $docid1; $docid2;
        $res = $db->getCollection('col1');
        $this->AssertEquals('',$db->getErrorMsg());
        $this->AssertEquals('col1',$res->name);
    }
    
    public function test_get_collection_not_found() {
        global $docStorage, $db, $col1, $col2, $docid1; $docid2;
        $res = $db->getCollection('col123');
        $this->AssertEquals('col123 not_found',$db->getErrorMsg());
        $this->AssertEquals('',$res->name);
    }
    
    public function test_create_collection3() {
        global $docStorage, $db, $col1, $col2, $col3, $docid1; $docid2;
        $col3 = $db->createCollection('col3',['id','f1','f2']);
        $this->AssertEquals('',$db->getErrorMsg());
    }
    
    public function test_drop_collection3() {
        global $docStorage, $db, $col1, $col2, $col3, $docid1; $docid2;
        $db->dropCollection($col3->id);
        $this->AssertEquals('',$db->getErrorMsg());
    }
    
    public function test_get_after_drop() {
        global $docStorage, $db, $col1, $col2, $col3, $docid1; $docid2;
        $res = $db->getCollection('col3');
        $this->AssertEquals('col3 not_found',$db->getErrorMsg());
    }
    
    public function test_recreate_drop_get() {
        global $docStorage, $db, $col1, $col2, $docid1; $docid2;
        $col3 = $db->createCollection('col3',['id','f1','f2']);
        $this->AssertEquals('',$db->getErrorMsg());
        
        $db->dropCollection($col3->id);
        $this->assertEquals('', $db->getErrorMsg());
        
        $res = $db->getCollection('col3');
        $this->AssertEquals('col3 not_found',$db->getErrorMsg());
    }
    
    // ======================== Collection test =====================
    
    public function test_add_document1() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $doc = new Document();
        $doc->ty = 'doc';
        $doc->field1 = '01';
        $doc->field2 = '02';
        $doc->field3 = '03';
        $docId1 = $col1->addDocument($doc);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertNotEquals('',$docId1);
    }
    
    public function test_add_document2() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $doc = new Document();
        $doc->ty = 'doc';
        $doc->field1 = '11';
        $doc->field2 = '12';
        $doc->field3 = '13';
        $docId2 = $col1->addDocument($doc);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertNotEquals('',$docId2);
    }
    public function test_add_document3() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $doc = new Document();
        $doc->ty = 'doc';
        $doc->field1 = '21';
        $doc->field2 = '22';
        $doc->field3 = '23';
        $res = $col1->addDocument($doc);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertNotEquals('',$res);
    }
    
    
    public function test_read_documents1() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->readDocuments('field1');
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(3,count($docs));
        $this->AssertEquals('01',$docs[0]->field1);
    }
    
    public function test_read_documents_pagination1() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->readDocuments('field1','ASC',1,100);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(2,count($docs));
        $this->AssertEquals('11',$docs[0]->field1);
    }
    
    public function test_read_documents_pagination2() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->readDocuments('field1','ASC',0,2);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(2,count($docs));
        $this->AssertEquals('01',$docs[0]->field1);
    }
    
    public function test_read_documents_pagination3() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->readDocuments('field1','DESC',0,2);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(2,count($docs));
        $this->AssertEquals('21',$docs[0]->field1);
    }
    
    public function test_read_documents_notindexed() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->readDocuments('field3');
        $this->AssertEquals('field3 not indexed',$col1->getErrorMsg());
        $this->AssertEquals(0,count($docs));
    }
    
    public function test_read_documents_pagination4() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->readDocuments('id','DESC',10,2);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(0,count($docs));
    }
    
    public function test_find_documents1() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->findDocuments('field1','21');
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(1,count($docs));
        $this->AssertEquals('21',$docs[0]->field1);
    }
    
    public function test_find_documents_notfound() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->findDocuments('field1','2wrr1');
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(0,count($docs));
    }
    
    public function test_find_documents_pagination1() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->findDocuments('field1','21','DESC',1,1);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(0,count($docs));
    }
    
    public function test_createIndex1() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $col1->createIndex('field3');
        $this->AssertEquals('',$col1->getErrorMsg());
    }
    
    public function test_createIndex_exists() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $col1->createIndex('id');
        $this->AssertEquals('id index is exists',$col1->getErrorMsg());
    }
    
    public function test_find_documents_inNewIndex() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $docs = $col1->findDocuments('field3','03');
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertEquals(1,count($docs));
    }
    
    public function test_add_document_notField2() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $doc = new Document();
        $doc->ty = 'doc';
        $doc->field1 = '31';
        $doc->field3 = '33';
        $res = $col1->addDocument($doc);
        $this->AssertEquals('',$col1->getErrorMsg());
        $this->AssertNotEquals('',$res);
    }

    public function test_counts() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $countId = $col1->count('id');
        $this->assertEquals(4,$countId);
        $countField2 = $col1->count('field2');
        $this->assertEquals(3,$countField2);
    }
    
    public function test_dropIndex() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $col1->dropIndex('field3');
        $this->AssertEquals('',$col1->getErrorMsg());
    }
    
    public function test_dropIndex_notfound() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $col1->dropIndex('xyz');
        $this->AssertEquals('xyz index is not exists',$col1->getErrorMsg());
    }
    
    public function test_dropIndex_id() {
        global $docStorage, $db, $col1, $col2, $docId1; $docId2;
        $col1->dropIndex('id');
        $this->AssertEquals('The id index can not dropped',$col1->getErrorMsg());
    }
    
    
    public function test_end() {
        global $docStorage, $db, $col1, $col2, $col3, $docid1; $docid2;
        
        $db->dropCollection($col1->id);
        $this->assertEquals('', $db->getErrorMsg());
        
        $db->dropCollection($col2->id);
        $this->assertEquals('', $db->getErrorMsg());
        
        $db->dropCollection($col3->id);
        $this->assertEquals('not_found', $db->getErrorMsg());
    }
    
 
}

?>