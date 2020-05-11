<?php
/**
 * Cat-DB noSQL database
 * @author utopszkij
 * @authorEmail tibor.fogler@gmail.com
 * @licence GNU/GPL
 *
 * classes and methods:
 *   Db
 *      public function createCollection($name, $indexes): Collection  !!! can not update name !!!!
 *      public function dropCollection($name): bool
 *      public function getCollection($name): Collection
 *      public function getErrorMsg(): string
 *   Collection
 *      public function addDocument($document): string
 *      public function updateDocument($oldDocument, $newDocument): string
 *      public function removeDocument($document): bool
 *      public function readDouments($fieldName, $order, $offset, $limit): array
 *      public function findDouments($fieldName, $value, $order, éoffset, $limit): array
 *      public function count($fieldName, $value): int
 *      public function createIndex($fieldName): bool
 *      public function dropIndex($fieldName): bool
 *      public function getErrorMsg(): string
 *
 *  if defined DOC_STORAGE_NAME use it else use included JsonDocStorage
 *  if defined KEY_STORAGE_NAME use it else use JsonKeyStorage (inluded in Btree.php)
 *  if defined KEY_CLASS_NAME use it else use Btree
 */
 
class DocStorage {
    
}

class JsonDocStorage extends DocStorage {
    private $docs = [];
    
    function __destruct() {
        $fp = fopen('json/docs.json','w+');
        fwrite($fp, JSON_encode($this->docs, JSON_PRETTY_PRINT));
        fclose($fp);
    }
    public function add(Document $document): string {
        $this->docs[] = $document;
        return (string)(count($this->docs) - 1);
    }
    public function update(Document $oldDocument, Document $newDocument): string {
        $this->docs[(int)$oldDocument->id] = $newDocument;
        return $oldDocument->id;
    }
    public function remove(Document $document): bool {
        $this->docs[(int)$document->id]->ty = 'del';
        return true;
    }
    public function read(String $id): Document {
        return $this->docs[(int)$id];
    }
    public function getErrorMsg(): string {
        return '';
    }
}
   
 class Document {
    public $id = '';
    public $ty = ''; // 'col'|'doc'|'del'
    public $colName = '';
}

class CollectionRec extends Document {
	public $indexes = []; // array of fieldName
	public $indexRoots = []; // array of Adresses  
}

class KeyRec {
	public $parent = '';
	public $key = '';
	public $value = '';
	public $left = '';
	public $right = '';
	public $deleted = false;
}

class Collection {
   public $db = false;
	public $id = '';
	public $name = '';
	public $indexes = [];  // array of Key processing object  
	private $errorMsg = '';
	private $docStorage = false;
	private $keyClassName = '';

	/**
	 * constructor
	 * @param string $name collection name
	 * @param array $indexes array of string fieldNames. must include 'id' !
	 * @param array $indexRoots array of string index tree root points
	 * @param JsonDocStorage|HoloDocStorage  $docStorage  data stroca ovject
	 * @param string $keyClassName  Key storage class name
	 */
	function __construct(string $name, 
	                       array $indexes, // array of string fieldNames  must constand 'id' !
	                       array $indexRoots, 
	                       DocStorage $docStorage, 
	                       string $keyClassName = 'Btree') {
	    $this->name = $name;
	    foreach ($indexes as $fieldName) {
	        $this->indexes[] = new $keyClassName($name, $fieldName);
	    }
	    for ($i=0; $i < count($this->indexes); $i++) {
	            $this->indexes[$i]->rootId = $indexRoots[$i];
	    }
	    $this->docStorage = $docStorage;
	    $this->keyClassName = $keyClassName;
	    $this->errorMsg = '';
	}

	/**
	 * check exists index?
	 * @param string $fieldName
	 * @return bool
	 */
	public function keyExists(string $fieldName): bool {
	    $result = false;
	    foreach ($this->indexes as $index) {
	        if ($index->fieldName == $fieldName) {
	            $result = true;
	        }
	    }
	    return $result;
	}

	/**
	 * add all index for one document
	 * @param Document $document
	 * @return bool
	 */
	private function addAllIndexes(Document $document): bool {
	   $this->errorMsg = '';
	   foreach ($this->indexes as $index) {
		    $fieldName = $index->fieldName;
		    if (isset($document->$fieldName)) {
		        $index->insert($document->id, $document->$fieldName);
		    }
		}
		return ($this->errorMsg == '');
	}

	/**
	 * update all indexes for one document
	 * @param Document $oldDocument
	 * @param Document $newDocument
	 * @return bool
	 */
	private function updateAllIndexes(Document $oldDocument, Document $newDocument): bool {
	    $this->errorMsg = '';
		foreach ($this->indexes as $index) {
		    $fieldName = $index->fieldName;
		    if (isset($oldDocument->$fieldName)) {
		        $oldValue = $oldDocument->$fieldName;
		    } else {
		        $oldValue = 'vhhrtzvbbbuztn876';
		    }
		    if (isset($newdDocument->$fieldName)) {
		        $newValue = $oldDocument->$fieldName;
		    } else {
		        $newValue = 'vhurewdbv77856bnoi';
		    }
		    // need to be modified?
		    if (($oldValue != $newValue) | ($oldDocument->id != $newDocument->id)) {
		        // delete old index
		        if (isset($oldDocument->$fieldName)) {
		            $item = $index->find($oldDocument->$fieldName);
    		        while ((!$item->deleted) & 
    		               ($item->value == $oldDocument->$fieldName) &
    		            ($item->key != $oldDocument->id)) {
    		            $item = $index->next();          
    		        }
    		        if (!$item->deleted) {
    		          $index->delete($item);
    		        }
    		    }
    		    // create new index
	   	        if (isset($newdDocument->$fieldName)) {
		          $index->insert($newDocument->id, $newDocument->$fieldname);
		        }
		    }
		}
		return ($this->errorMsg == '');
	}

	/**
	 * remova all indexes for ine document
	 * @param Document $document
	 * @return bool
	 */
	private function removeAllIndexes(Document $document): bool {
	   $this->errorMsg = '';
	   foreach ($this->indexes as $index) {
	       $fieldName = $index->fieldName;
	       if (isset($document->$fieldName)) {
	           $item = $index->find($document->$fieldName);
	           while ((!$item->deleted) &
	               ($item->value == $document->$fieldName) &
	               ($item->key != $document->id)) {
	                   $item = $index->next();
	           }
	           if (!$item->deleted) {
	                   $index->delete($item);
	           }
	       }
	   }
	   return ($this->errorMsg == '');
	}

	/**
	 * add new document
	 * @param Document $document
	 * @return string new $document->id
	 */
	public function addDocument(Document &$document): string {
	    $this->errorMsg = '';
	    $docId = '';
	    $document->id = '';
	    $document->ty = 'doc';
	    $document->colName = $this->name;
	    $docId = $this->docStorage->add($document);
		$this->errorMsg = $this->docStorage->getErrorMsg();
		if ($this->errorMsg == '') {
		   $document->id = $docId;
		   $this->addAllIndexes($document);
		}
		return $docId;
	}

	/**
	 * update one exists document
	 * @param Document $oldDocument
	 * @param Document $newDocument
	 * @return bool
	 */
	public function updateDocument(Document $oldDocument, Document &$newDocument): bool {
	    $this->errorMsg = '';
	    $newDocument->id = '';
	    $newDocument->ty = 'doc';
	    $newDocument->colName = $this->name;
	    $newDocId = $this->docStorage->update($oldDocument, $newDocument);
		$this->errorMsg = $this->docStorage->getErrorMsg();
		if ($this->errorMsg == '') {
		   $newDocument->id = $newDocId;
		   $this->updateAllIndexes($oldDocument, $newDocument);
		}
		return ($this->errorMsg == '');
	}

	/**
	 * remove one exists document
	 * @param Document $document
	 * @return bool
	 */
	public function removeDocument(Document $document): bool {
	    $this->errorMsg = '';
		$this->docStorage->remove($document);
		$this->errorMsg = $this->docStorage->getErrorMsg();
		if ($this->errorMsg == '') {
		   $this->removeAllIndexes($document);
		}
		return ($this->errorMsg == '');
	}

	/**
	 * read documents use pagination order by one fieldName ASC or DESC
	 * @param string $fieldName
	 * @param string $order
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function readDocuments(string $fieldName, string $order = 'ASC',
	           int $offset = 0, int $limit = 0): array {
	   $this->errorMsg = '';
	   $result = [];
	   if (!$this->keyExists($fieldName)) {
	       $this->errorMsg=$fieldName.' not indexed';
	   }
	   foreach ($this->indexes as $index) {
           if ($index->fieldName == $fieldName) {
               if ($order == 'ASC') {
                   $i = 0;
                   $j = 0;
                   $item = $index->first();
                   while ((!$item->deleted) & (($j < $limit) | $limit == 0)) {
                       if ($i >= $offset) {
                           $res = $this->docStorage->read($item->key);
                           if ($res->ty == 'doc') {
                             $result[] = $res;
                             $j++;
                           }
                       }
                       $item = $index->next($item);
                       $i++;
                   }
               } else {
                   $i = 0;
                   $j = 0;
                   $item = $index->last();
                   while ((!$item->deleted) & ($j < $limit)) {
                       if ($i >= $offset) {
                           $res = $this->docStorage->read($item->key);
                           if ($res->ty == 'doc') {
                               $result[] = $res;
                               $j++;
                           }
                       }
                       $item = $index->previos($item);
                       $i++;
                   }
               }
               
           }
       }
       return $result;
	}

	/**
	 * find documents by filedname==value, pagination, ordering
	 * @param string $fieldName
	 * @param string $value
	 * @param string $order
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function findDocuments(string $fieldName, string $value, string $order = 'ASC',
	       int $offset = 0, int $limit = 0): array {
	    $this->errorMsg = '';
	    $result = [];
	    if (!$this->keyExists($fieldName)) {
	        $this->errorMsg=$fieldName.' not indexed';
	    }
	    foreach ($this->indexes as $index) {
	        if ($index->fieldName == $fieldName) {
	            if ($order == 'ASC') {
	                $i = 0;
	                $j = 0;
	                $item = $index->first();
	                while ((!$item->deleted) & (($j < $limit) | ($limit == 0))) {
	                    if ($item->value == $value) {
	                       if ($i >= $offset) {
	                           $res = $this->docStorage->read($item->key);
	                           if ($res->ty == 'doc') {
	                               $result[] = $res;
	                               $j++;
	                           }
	                       }
	                       $i++;
	                    }
	                    $item = $index->next($item);
	                }
	            } else {
	                $i = 0;
	                $j = 0;
	                $item = $index->last();
	                while ((!$item->deleted) & ($j < $limit)) {
	                    if ($item->value == $value) {
	                       if ($i >= $offset) {
	                            $res = $this->docStorage->read($item->key);
	                            if ($res->ty == 'doc') {
    	                            $result[] = $res;
	                                $j++;
	                            }
	                       }
	                       $i++;
	                    }
	                    $item = $index->previos($item);
	                }
	            }
	            
	        }
	    } // foreach $this->indexes
	    return $result;
	}

	/**
	 * read count for fieldName must it is a indexed field
	 * @param string $fieldName
	 * @param string $value optional
	 * @return int
	 */
	public function count(string $fieldName, $value = false): int {
	    $this->errorMsg = '';
	    $result = 0;
	    if (!$this->keyExists($fieldName)) {
	        $this->errorMsg=$fieldName.' not indexed';
	    }
	    foreach ($this->indexes as $index) {
	        if ($index->fieldName == $fieldName) {
	            $result = $index->count($value);
	        }
	    }
	    return $result;
	}

	/**
	 * create index for fieldName from all documents 
	 * @param string $fieldName
	 * @return bool
	 */
	public function createIndex(string $fieldName): bool {
	    $this->errorMsg = '';
	    
	    $oldCollectionRec = $this->docStorage->read($this->id);
	    if (in_array($fieldName, $oldCollectionRec->indexes)) {
	        // index exists
	        $this->errorMsg = $fieldName.' index is exists';
	        return true;
	    }
	    
	    // create new Keyprocess object
	    $keyClassName = $this->keyClassName;
	    $index = new $keyClassName($this->name, $fieldName);
	    $index->init();
	    $this->indexes[] = $index;
	    
	    // create indexItem for documents
	    $count = $this->count('id');
		$offset = 0;
		while ($offset < $count) {
			$documents = $this->readDocuments('id', 'ASC', $offset, 100);
			foreach ($documents as $document) {
			    if (isset($document->$fieldName)) {
			     $index->insert($document->id, $document->$fieldName);
			    }
			}
			$offset = $offset + 100;
		}

		// update collectionRec
		$collectionRec = new CollectionRec();
		$collectionRec->ty = 'col';
		$collectionRec->id = $this->id;
		$collectionRec->colName = $this->name;
		$collectionRec->indexes = $oldCollectionRec->indexes;
		$collectionRec->indexes[] = $fieldName;
		$collectionRec->indexRoots = $oldCollectionRec->indexRoots;
		$collectionRec->indexRoots[] = $this->indexes[count($this->indexes) - 1]->rootId;
		$collectionRec->id = $this->docStorage->update($oldCollectionRec, $collectionRec);
		
		return ($this->errorMsg == '');
	}

	/**
	 * drop index 
	 * @param string $fieldName
	 * @param bool $updateCollectionRec
	 * @return bool
	 */
	public function dropIndex(string $fieldName, bool $updateCollectionRec = true): bool {
	    $this->errorMsg = '';
	    if ($fieldName == 'id') {
	        $this->errorMsg = 'The id index can not dropped';
	        return false;
	    }
	    $oldCollectionRec = $this->docStorage->read($this->id);
	    if (!in_array($fieldName, $oldCollectionRec->indexes)) {
	        // not exists
	        $this->errorMsg = $fieldName.' index is not exists';
	        return true;
	    }
	    
	    // delete all index item entry from this index
        $i = 0;
        $j = 0; // actual index is $this->index[$j]
	    foreach ($this->indexes as $index) {
	        if ($index->fieldName == $fieldName) {
	            $count = $this->count('id');
	            $offset = 0;
	            while ($offset < $count) {
	                $documents = $this->readDocuments('id', 'ASC', $offset, 100);
	                foreach ($documents as $document) {
	                    if (isset($document->$fieldName)) {
	                        $item = $index->find($document->$fieldName);
	                        while ((!$item->deleted) &
	                            ($item->value == $document->$fieldName) &
	                            ($item->key != $document->id)) {
	                                $item = $index->next();
	                        }
	                        if (!$item->deleted) {
	                           $index->delete($item);
	                        }
	                    }
	                }
	                $offset = $offset + 100;
	            }
	            $j = $i;
	        } // index->fieldName == $fieldName
	        $i++;
	    } // foreach;
	    
        // delete index from $this->indexes
        array_splice($this->indexes, $j,1);
        
        // update collectionRec
        if ($updateCollectionRec) {
            $collectionRec = new CollectionRec();
            $collectionRec->ty = 'col';
    		$collectionRec->id = $this->id;
    		$collectionRec->colName = $this->name;
    		$collectionRec->indexes = $oldCollectionRec->indexes;
    		$collectionRec->indexRoots = $oldCollectionRec->indexRoots;
    		$i = array_search($fieldName,$collectionRec->indexes);
    		array_splice($collectionRec->indexes, $i, 1);
    		array_splice($collectionRec->indexRoots, $i, 1);
    		$collectionRec->id = $this->docStorage->update($oldCollectionRec, $collectionRec);
        }
        
        return ($this->errorMsg == '');
	}

	/**
	* get last actionn errorMsg
	* @return string if succes it is ''
	*/
	public function getErrorMsg() : string {
		return $this->errorMsg;
	}
}

class Db {
	private $errorMsg = "";
	private $docStorage = false;
	private $keyClassName = '';
	private $colNamesIndex;

	/**
	 * constructor
	 * @param string $docStoragename  optional
	 * @param string $keyClassname optional
	 */
	function __construct($docStorageName = '',  $keyClassName = '') {
	    if ($docStorageName == '') {
    	    if (defined('DOC_STORAGE_NEME')) {
    	        $docStorageName = DOC_STORAGE_NEME;
    	    } else {
    	        $docStorageName = 'JsonDocStorage';
    	    }
	    }
	    if ($keyClassName == '') {
    	    if (defined('KEY_CLASS_NEME')) {
    	        $this->keyClassName = KEY_CLASS_NAME;
    	    } else {
    	        $this->keyClassName = 'Btree';
    	    }
	    }
	   $keyClassName = $this->keyClassName;
	   $this->docStorage = new $docStorageName ();
	   $this->errorMsg = '';
	   $this->colNamesIndex = new $keyClassName ('db','colNames');
	   $this->colNamesIndex->init(); // holochain esetén beolvas a DHT -ből
	}

	/**
	 * get last action errorMsg, if success return ''
	 * @return string
	 */
	public function getErrorMsg(): string {
	    return $this->errorMsg;
	}

	/**
	* create new collection
	* @param string $name
	* @param array $indexes  FieldNames
	* @return Collection or false
	*/
	public function createCollection(string $name, array $indexes): Collection {
	    $result =  new Collection('',[],[],$this->docStorage, $this->keyClassName);
	    $this->errorMsg = '';
	    // van már ilyen nevü?
        $item = $this->colNamesIndex->find( $name );
	    if ($item->deleted) {
	        if (!in_array('id',$indexes)) {
	            $indexes[] = 'id';
	        }
	        $collectionRec = new CollectionRec();
	        $collectionRec->ty = 'col';
	        $collectionRec->id = '';
	        $collectionRec->colName = $name;
	        $collectionRec->indexes = $indexes;
	        $collectionRec->indexRoots = [];
	        // indexek inicializálása
	        $keyClassName = $this->keyClassName;
	        foreach ($indexes as $fieldName) {
	            $index = new $keyClassName($name, $fieldName);
	            $index->init();
	            $collectionRec->indexRoots[] = $index->rootId;
	        }
	        $collectionRec->id = (string)$this->docStorage->add($collectionRec);
	        $this->errorMsg = $this->docStorage->getErrorMsg();

	        if ($this->errorMsg == '') {
	           $this->colNamesIndex->insert($collectionRec->id, $name);
	        }
	        if ($this->errorMsg == '') {
	           $result =  new Collection($name,
	               $collectionRec->indexes,
	               $collectionRec->indexRoots,
	               $this->docStorage, 
	               $this->keyClassName);
	           $result->id = (string)$collectionRec->id;
	           $result->db = $this;
	        }
	    } else {
	        $this->errorMsg = $name.' collection is exists';
	    }
	    return $result;
	}

	/**
	* get exists collection
	* @param string $name
	* @return Collection or false
	*/
	public function getCollection(string $name): Collection {
	    $this->errorMsg = '';
	    $result = new Collection('',[],[],$this->docStorage, $this->keyClassName);
	    $result->db = $this;
	    $item = $this->colNamesIndex->find($name);
	    if ($item->deleted) {
	        $this->errorMsg = $name.' not_found';
	    } else {
	        $collectionRec = $this->docStorage->read($item->key);
	        if (isset($collectionRec->indexes)) {
    	        $this->errorMsg = $this->docStorage->getErrorMsg();
    	        $result = new Collection($name, 
    	            $collectionRec->indexes,
    	            $collectionRec->indexRoots,
    	            $this->docStorage, 
    	            $this->keyClassName);
    	        $result->id = $item->key;
	        }
	    }
	    return $result;
	}

	/**
	* remove exists collection
	* @param string $id
	* @return bool
	*/
	public function dropCollection(string $id): bool {
       $this->errorMsg = '';
	   $result = false;
       $collectionRec = $this->docStorage->read($id);
	   $collection = $this->getCollection($collectionRec->colName);
       $this->errorMsg = $this->docStorage->getErrorMsg();
       if ($this->errorMsg == '') {
            // remove collection' indexes
            foreach ($collection->indexes as $index) {
                $collection->dropIndex($index->fieldName, false);
            }
            // remove from colName index

            $item = $this->colNamesIndex->find($collectionRec->colName);
            $res = $this->colNamesIndex->delete($item);
            // delete collection rec
            $this->docStorage->remove($collectionRec);
        }
	    return $result;
	}
}
?>
