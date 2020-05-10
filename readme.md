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
- store data in DHT in encrypted by AES-128-CTR.

## Overview

![](https://github.com/utopszkij/holo_nosql/blob/master/doc/holodb-koncepcio.png) 


![](https://github.com/utopszkij/holo_nosql/blob/master/doc/holodb-sw.png) 

## Licence
GNU/GPL

## Author
Tibor Fogler (utopszkij)
tibor.fogler@gmail.com

## Classes and methods:
### Db
-       public function createCollection($name, $indexes): Collection  !!! 
-       public function dropCollection($name): bool
-       public function getCollection($name): Collection
-       public function getErrorMsg(): string
###  Collection
-       public function addDocument($document): string
-       public function updateDocument($oldDocument, $newDocument): string
-       public function removeDocument($document): bool
-       public function readDouments($fieldName, $order, $offset, $limit): array
-       public function findDouments($fieldName, $value, $order, éoffset, $limit): array
-       public function count($fieldName, $value): int
-       public function createIndex($fieldName): bool
-       public function dropIndex($fieldName): bool
-       public function getErrorMsg(): string
 
  if defined DOC_STORAGE_NAME use it else use included JsonDocStorage
  
  if defined KEY_STORAGE_NAME use it else use JsonKeyStorage (inluded in Btree.php)
 
  if defined KEY_CLASS_NAME use it else use Btree
 