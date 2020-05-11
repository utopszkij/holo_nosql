# Minimal noSQL database data store in holochain DHT

## Overview

 holo_nosql_interface  for PHP 
 
## Version
 
 Aplha 1.0  not stabile
 
## Language
 
 PHP, holochain RUST

## Request 

- https://github.com/holochain/holonix
- php 7.4.5,  include curl, crypt module
- phpunit 6.5.5
- web server include php module

see: https://github.com/utopszkij/holo_nosql/blob/master/doc/readme.md

## Properties 

- CRUD funcions (create,read,update,delete),
- Collection / Document data structure,
- create collection and defined indexed fileds,
- drop collection, delete all documents from this collection,
- get collection by name,
- add document into one exists collection, create index for indexed fields,
- update existng document, update exists indexes,
- delete existing docuemnt, remove exists indexes,
- find document in one collection filtered by indexed field value, use pagination, ASC/DESC ordering
- read documents set from one collection by indexed field, use pagination and ASC/DESC ordering,
- get documents'count in one collection where one indexed field exists,
- create index, drop index into existing collections and documents,


- store in holochain DHT: "btreeItem", "collection info", "document" 
- "document" and "collection info" is encrypted by AES-128-CTR method.

## Use Example
```
<?php
define('DOC_STORAGE_NEME','HoloDocStorage');
define('KEY_STORAGE_NAME','HoloBtreeStorage');
define('KEY_CLASS_NAME','Btree');
define('HOLOPSW','123456');
define('HOLOURL','http://127.0.0.1:8888');
define('HOLOFULLCHAIN',true);  // create or not chain from all document
define('HOLOINSTANCE','test-instance');

include_once './btree.php';
include_once './catdb.php';
include_once './holo_btree_storage.php';
include_once './holo_doc_storage.php';

// create database object
$db = new Db();

// create new collection into DHT
$col1 = $db->createCollection('colName1',['id','field1','field2']);

// create new document into DHT
$doc = new Document();
$doc->ty = 'doc';
$doc->field1 = '01';
$doc->field2 = '02';
$doc->field3 = '03';
$docId1 = $col1->addDocument($doc);

// read documents from DHT
$docs = $col1->findDocuments('field1','01');
foreach ($docs as $doc) {
	echo JSON_encode($doc)."\n";
}

// drop collection
$db->dropCollection($col1->id);

?>
```

## Overview

![](https://github.com/utopszkij/holo_nosql/blob/master/doc/holodb-koncepcio.png) 


![](https://github.com/utopszkij/holo_nosql/blob/master/doc/holodb-sw.png) 

## Licence
GNU/GPL

## Author

Tibor Fogler (utopszkij)
tibor.fogler@gmail.com
https://github.com/utopszkij


## Classes and methods:

### CollectionRec
- id
- ty = 'col'
- colName
- previos   only if HOLOFULLCHAIN == true
- indexFields
- indexRoots

### Document
- id 
- ty = 'doc'
- colName
- previos   only if HOLOFULLCHAIN == true
- filed1, field2 ,....


### Db
-       public function createCollection(string $name, array $indexes): Collection   
-       public function dropCollection(string $name): bool
-       public function getCollection(string $name): Collection
-       public function getErrorMsg(): string
###  Collection
-       public function addDocument(Document $document): string
-       public function updateDocument(Document $oldDocument, Document $newDocument): string
-       public function removeDocument(Document $document): bool
-       public function readDouments(string $fieldName, string $order, int $offset, int $limit): array ($oreder is 'ASC' or "DESC")
-       public function findDouments(string $fieldName, string $value, string $order, int $offset, int $limit): array ($oreder is 'ASC' or "DESC")
-       public function count(string $fieldName, string $value): int (		$value is filter fieldName'value or "Any")
-       public function createIndex(string $fieldName): bool
-       public function dropIndex(string $fieldName): bool
-       public function getErrorMsg(): string
 
  if defined DOC_STORAGE_NAME use it else use included JsonDocStorage
  
  if defined KEY_STORAGE_NAME use it else use JsonKeyStorage (inluded in Btree.php)
 
  if defined KEY_CLASS_NAME use it else use Btree
 