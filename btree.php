<?php
/**
* BTree processing
*
* @Author Fogler Tibor
* @AuthorEmail tibor.fogler@gmail.com
* @Licence GNU/GPL
*
* if defined KEY_STORAGE_NAME then use it, else use included JSON storage 
*/


/**
* Btree node
*/
class BtreeItem {
	public $parent = '';
	public $id = ''; 
	public $key = '';
	public $value = '';
	public $deleted = false; 
	public $left = '';
	public $right = ''; 
}

/**
* BtreeItem storage class
* request 
*     BtreeItem class
*     JSONPATH constant
*/
class JsonKeyStorage {
	private $items = array();
	private $name = 'btree.json';		
		
	function __construct($name = 'btree') {
		$this->name = $name;	
	}		
		
	/**
	* read item from storage
	* @param string $id
	* @return BtreeItem if not found id=''
	*/	
	public function get(string $id): BtreeItem  {
		if ((int)$id < count($this->items)) {
			$result = new BtreeItem();
			foreach ($result as $fn => $fv) {
				$result->$fn = $this->items[(int)$id]->$fn;			
			}
			return $result;
		} else {
			return new BtreeItem();	
		}
	}	

	/**
	* storage new item into storage
	* @param BtreeItem item
	* @return string  new id
	*/
	public function add(BtreeItem $item): string {
		$item->id = (string)count($this->items);
		$this->items[] = $item;
		return $item->id;
	}

	/**
	* delete one item
	* @param BtreeItem $item
	*/
	public function delete(BtreeItem $item) {
		$item->deleted = true;
		$this->update($item);	
	}
	
	/**
	* set right in item
	* @param BtreeItem $item
	* @param string $right
	*/
	public function setRight(BtreeItem $item, string $right) {
		$item->right = $right;	
		$this->update($item);	
	}

	/**
	* set left in item
	* @param BtreeItem $item
	* @param string $left
	*/
	public function setLeft(BtreeItem $item, string $left) {
		$item->left = $left;	
		$this->update($item);	
	}

	/**
	* btree empty?
	* @return bool
	*/
	public function isEmpty(): bool {
		return (count($this->items) == 0);	
	}	
	
	/**
	* get root item 
	* @return BtreeItem
	*/
	public function getRoot(): BtreeItem {
		if ($this->isEmpty()) {
		  return new BtreeItem();	
		} else {
			$result = new BtreeItem();
			foreach ($result as $fn => $fv) {
				$result->$fn = $this->items[0]->$fn;			
			}
		  return $result;
		}	
	}
	
	public function init(): string {
		return '0';	
	}

	/**
	* delete all items
	*/
	public function clear() {
		$this->items = array();	
	}

	/**
	* dump items to json string
	* @return string
	*/
	public function dump() {
		return JSON_encode($this->items, JSON_PRETTY_PRINT);	
	}
	
	/**
	* load items from phisical storage
	*/
	public function load() {
		$fileName = 'json/'.$this->name.'.json';
		if (file_exists($fileName)) {
			$this->items = JSON_decode(implode('', file($fileName)));		
		}
	}
	
	/**
	* save items into phisical storage
	*/
	public function save() {
		$fileName = 'json/'.$this->name.'.json';
		$fp = fopen($fileName,'w+');
		fwrite($fp, JSON_encode($this->items,JSON_PRETTY_PRINT));
		fclose($fp);
	}
    
	/**
	 * lock database
	 * @return bool
	 */
	public function lock(): bool {
	    return true;
	}
	
	/**
	 * unlock database
	 */
	public function unlock() {
	    
	}
	
	/**
	* update one item in storage
	* @param BtreeItem item
	* @return string new id
	*/
	private function update(BtreeItem $item): string {
		if ((int)$item->id < count($this->items)) {
			 $this->items[(int)$item->id] = $item;
			 return $item->id;
		} else {
			return false;		
		}
	}
	
}

/**
* btree processing class
*/
class Btree {
	public $eof = false;
	public $bof = false;
	public $colName = '';
	public $fieldName = '';
	public $rootId = '';
	private $errorMsg = '';
	private $storage = false;
	
	function __construct(string $colName = '', string $fieldName= '') {
		$this->colName = $colName;		
		$this->fieldName = $fieldName;
		if (defined('KEY_STORAGE_NAME')) {
		    $keyStorageName = KEY_STORAGE_NAME;
		} else {
		    $keyStorageName = 'JsonKeyStorage';
		}
		$this->storage = new $keyStorageName($this->colName.'_'.$this->fieldName);		
	}	
	
	function __destruct() {
		$this->storage->save();	
	}
	
	/**
	* create tree root item 
	*/
	public function init() {
		$this->rootId = $this->storage->init();	
	}
	
	/**
	* delete full tree
	*/
	public function clear() {
		$this->storage->clear();	
	}
	
	/**
	* insert item into btree
	* @param string $key
	* @param string value
	* @return string  new id
	*/
	public function insert(string $key, string $value): string {
		$item = new BtreeItem();
		$item->key = $key;
		$item->value = $value;		
		if ($this->storage->isEmpty()) {
			// this is root item
			$newId = $this->storage->add($item);
		} else {
			$w = $this->storage->getRoot();
			$end = false;
			while (!$end) {
				if ($value >= $w->value) {
					if ($w->right > 0) {
						$w = $this->storage->get($w->right);
					} else {
						// insert w.right
						$item->parent = $w->id;
						$newId = $this->storage->add($item);
						$w->right = $newId;
						$this->storage->setRight($w, $newId);
						$end = true;
					}
				} else {
					if ($w->left > 0) {
						$w = $this->storage->get($w->left);
					} else {
						// insert left
						$item->parent = $w->id;
						$newId = $this->storage->add($item);
						$w->left = $newId;
						$this->storage->setLeft($w, $newId);
						$end = true;
					}
				}			
			} // while
		} // not empty	
		return $newId;		
	} // insert function
	
	/**
	* find value in btree
	* @param string $value
	* @return BtreeItem  if not fount id=''
	*/
	public function find(string $value): BtreeItem {
		$result = new BtreeItem();
		$result->deleted = true;
		if ($this->storage->isEmpty()) {
			return $result;
		} else {
			$w = $this->storage->getRoot();
			$end = false;
			while (!$end) {
				if ($w->value == $value) {
						$result = $w;						
						$end = true;						
				} else if ($value > $w->value) {
					if ($w->right > 0) {
						$w = $this->storage->get($w->right);
					} else {
						if ($w->value == $value) {
							$result = $w;						
						}
						$end = true;						
					}
				} else {
					if ($w->left > 0) {
						$w = $this->storage->get($w->left);
					} else {
						if ($w->value == $value) {
							$result = $w;						
						}
						$end = true;
					}
				}			
			} // while
			if ($result->deleted) {
				$result = $this->next($result);			
			}
			if ($result->value != $value) {
				$result = new BtreeItem();	
				$result->deleted = true;		
			}
			return $result;		
		} // not empty	
	}
	
	/**
	* delete one item from btree
	* @param BtreeItem
	*/
	public function delete($item) {
		$item->deleted = true;
		$this->storage->delete($item);
	}
	
	/**
	* get first item from btree
	* @param BtreeItem root optional, default root item
	* @return BtreeItem  if not found id=''
	*/
	public function first($root = false): BtreeItem {
		$result = new BtreeItem();
		$result->deleted = true;
		if (!$this->storage->isEmpty()) {
			if ($root) {
				$w = $root;
			} else {
				$w = $this->storage->getRoot();
			}	
			while ($w->left > 0) {
				$w = $this->storage->get($w->left);			
			}
			$result = $w;
			if ($result->deleted) {
				$result = $this->next($result);			
			}
		} // not empty
		return $result;
	}

	/**
	* get last item from btree
	* @param BtreeItem root optional, default root item
	* @return BtreeItem  if not found id=''
	*/
	public function last($root = false): BtreeItem {
		$result = new BtreeItem();
		$result->deleted = true;
		if (!$this->storage->isEmpty()) {
			if ($root) {
				$w = $root;
			} else {
				$w = $this->storage->getRoot();
			}	
			while ($w->right > 0) {
				$w = $this->storage->get($w->right);			
			}
			$result = $w;
			if ($result->deleted) {
				$result = $this->previos($result);			
			}
		} // not empty
		return $result;
	}
	
	/**
	* get next item from btree
	* @param BtreeItem $item
	* @return BtreeItem  if not found id=''
	*/
	public function next(BtreeItem $item): BtreeItem {
		$this->eof = false;
		$result = new BtreeItem();
		$result->deleted = true;
		$startId = $item->id;
		$end = false;
		if ($item->right > 0) {
			$item = $this->storage->get($item->right);
			$result = $this->first($item);
		} else {
			while (($item->parent != '') & (!$end)) {
				$item = $this->storage->get($item->parent);
				if ($item->left == $startId) {
					$result = $item;
					$end = true;
				} else {
					$startId = $item->id;
				}
			}
			if ($end) {
				while (($result->deleted) & (!$this->eof)) {
					$result = $this->next($result);				
				}			
			} else {
				$this->eof = true;			
			}
		}
		return $result;
	}

	/**
	* get previos item from btree
	* @param BtreeItem $item
	* @return BtreeItem  if not found id=''
	*/
	public function previos(BtreeItem $item): BtreeItem {
		$this->bof = false;
		$result = new BtreeItem();
		$result->deleted = true;
		$startId = $item->id;
		$end = false;
		if ($item->left > 0) {
			$item = $this->storage->get($item->left);
			$result = $this->last($item);
		} else {
			while (($item->parent != '') & (!$end)) {
				$item = $this->storage->get($item->parent);
				if ($item->right == $startId) {
					$result = $item;
					$end = true;
				} else {
					$startId = $item->id;
				}
			}
			if ($end) {
				while (($result->deleted) & (!$this->bof)) {
					$result = $this->previos($result);				
				}			
			} else {
				$this->bof = true;			
			}
		}
		return $result;
	}
	
	/**
	* get item count from btree
	* @param string $value optional, default all item
	* @return int
	*/
	public function count($value = false): int {
		$result = 0;
		if (!$this->storage->isEmpty()) {
			$this->eof = false;
			$item = $this->first();
			while ((!$this->eof) & ($item->id != '')) {
				if ((($value == $item->value) | ($value == false)) &
				    ($item->deleted == false)
				   ) {
					$result++;
				}
				$item = $this->next($item);
			}
		}
		return $result;
	}
	
	/**
	 * return last action errorMsg, if success return ''
	 * @return string
	 */
	public function getErrorMsg(): string {
	    return $this->errorMsg();
	}
	
	/**
	* echo items in json format
	*/
	public function testEcho() {
		echo $this->storage->dump();	
	}
	
}
?>