<?php

/**
* Btree storage in holochain DHT
*
* requested constants: HOLOPSW, HOLOURL, HOLOINSTANCE
*
* DHT-structure:
* 
* root_btree_item  {key:"btree-root", value:"btreeName", parent:"", leftId:"", rightId:"", deleted:true}
*         +---link_lrdu---tag='del'
*         +---link_psw----tag='xxxxx'   target=self   only one 
*         +---link_lock---tag='dblock' target=self    only one 
*       a root_btree colName alapján collection tipusú item rekordokra mutat
*       vany egy "transactions_log" collection ami indexelve van a starttime -re is
*       
* DHT definition
* --------------
*     Entry
*        item
*     Link
*        link_psw  base: root_item, tag:'....', target=root_item
*        link_lrdu base: item, tag:'L'|'R'|'D'|'U', target= item
*       
*     public DNA funkcions
*        add_item(psw,datastr)           
*        get_item(psw,id)                
*        get_lrdu(psw,base)             
*        add_lrdu(psw,base, target,tag) 
*        del_ldru(psw,base, target,tag)       
*        set_psw(psw)
*        get_root()
*
* Logical database shema
* ----------------
*   root_item
*       +--(col_name)-------> collection
*                              |      |
*                           btree1   btree2
*                                     +----(value)---> document
*
* Logical rekords  (all type store in "Item")
* ----------------
* btreeItem  {parent, key, value) 
* collection {name, indexNames, indexRoots}  
* document   {colName, data} 
*/
class HoloBtreeStorage {
	public $name = 'btree';
	public $rootId = '';
	private $errorMsg = '';
		
	function __construct(string $name) {
	    $this->name = $name;
	    $this->rootId = '';
	    $this->errorMsg = '';
	}	
	
	public function getErrorMsg() {
	    return $this->errorMsg;
	}

	/**
	* @param string $zome
	* @param string $fun
	* @param array $par ["par1" => "...", "par2" => "...", ...]
	* @return object
	*/
	function dna_call(string $fun, array $par) {
	    if ($fun != 'set_psw') {
	        $par['psw'] = HOLOPSW;
	    }
	    $ch = curl_init();
		$opar = new stdClass();
		foreach ($par as $fn => $fv) {
			$opar->$fn = $fv;
		}
		
		curl_setopt($ch, CURLOPT_URL,HOLOURL);  
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		curl_setopt($ch, CURLOPT_POST, count($par));
		curl_setopt($ch, CURLOPT_POSTFIELDS,
		    '{"id": 0, 
              "jsonrpc": "2.0",
			  "method": "call",
		 	  "params": {"instance_id": "'.HOLOINSTANCE.'",
		 	             "zome": "holodb",
		 	             "function": "'.$fun.'",
		 				 "args": '.JSON_encode($opar).'
		 				}
		 	  }');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		curl_close ($ch);
		$result2 = JSON_decode(urldecode($result)); // {... ,result:"{....}", .... }
		if (isset($result2->result)) {
			$result3 = $result2->result;
			$result4 = JSON_decode($result3);
		} else {
			$result4 = $result2;
		}
		return $result4;
	}
		
		
	/**
	* read item from storage
	* @param string $id
	* @return BtreeItem if not found id=''
	*/	
	public function get(string $id): BtreeItem  {
	    $this->errorMsg = '';
	    // Entry olvasás a DHT -ből
		$result = new BtreeItem();
		$result->deleted = false;
		$res = $this->dna_call('get_item', ["id" => $id]);
		if (isset($res->Ok)) {
		  $encrypted = $res->Ok->App[1];
		  if (substr($encrypted,0,1) != '{') {
echo "\n encrypted=".$encrypted."\n";		  
		      $decryption_iv = '1201586891011121';
		      $dataStr = openssl_decrypt ($encrypted, "AES-128-CTR", HOLOPSW, 0, $decryption_iv);
echo "\n dataStr=".$dataStr."\n";
		  } else {
		      $dataStr = $encrypted;
		  }
		  $item = JSON_decode($dataStr);  
	      foreach ($item as $fn => $fv) {
	          $result->$fn = $fv;
	      }
	      $result->id = $id;
	      $result->deleted = false;
	      $res = $this->dna_call('get_lrdu',["base" => $id]);
	      if (isset($res->Ok)) {
	          $links = $res->Ok->links;
	          foreach ($links as $link)  {
	              if ($link->tag == 'L') {
	                  $result->left = $link->address;
	              }
	              if ($link->tag == 'R') {
	                  $result->right = $link->address;
	              }
	              if ($link->tag == 'D') {
                        $result->deleted = true;	                  
	              }
	              if ($link->tag == 'U') {
	                  $res = $this->dna_call('get_item', ["id" => $link->address]);
	                  if (isset($res->Ok)) {
	                      $item = JSON_decode($res->ok->App['item']);
	                      foreach ($item as $fn => $fv) {
	                          $result->$fn = $fv;
	                      }
	                  } else {
	                      $this->errorMsg = JSON_encode($res);
	                  }
	              }
	          } // foreach links
	      } else {
	          $this->errorMsg = JSON_encode($res);
	          $result->deleted = true;
	      } // succes link_lrdu read
		} else {
		    $this->errorMsg = 'not_found';
		    $result->deleted = true;
		} // succes item read
	    return $result;
	}	

	/**
	* storage new item into storage
	* @param BtreeItem item
	* @return string  new id
	*/
	public function add(BtreeItem $item): string {
	    $this->errorMsg = '';
	    // Item kialakitása
		$dhtItem = new stdClass();
		$dhtItem->key = $item->key;
		$dhtItem->value = $item->value;
		$dhtItem->parent = $item->parent;
		$s = JSON_encode($dhtItem);
		$encryption_iv = '1201586891011121';
		$encrypted = openssl_encrypt($s, "AES-128-CTR", HOLOPSW, 0, $encryption_iv);
		// item kitárolása 
		$res = $this->dna_call('add_item',["pdatastr" => $encrypted]); 
		if (isset($res->Ok)) {
		    // hozzá tartozó link_lrdu -k törlése
		    $res1 = $this->dna_call('get_lrdu',["base" => $res->Ok]);
		    if (isset($res1->Ok)) {
		        $links = $res1->Ok->links;
		        foreach ($links as $link) {
		            $res2 = $this->dna_call('del_lrdu',
		                ["base" => $res->Ok, "target" => $link->address, "tag" => $link->tag]); 
		            if (!isset($res2->Ok)) {
		                $this->errorMsg = JSON_encode($res);
		            }
		        }
		    }
		} else {
		    $this->errorMsg = JSON_encode($res);
		    return '';
		}
		// return new address
		return $res->Ok;
	}

	/**
	* delete one item
	* @param BtreeItem $item
	*/
	public function delete(BtreeItem $item): bool {
	    $this->errorMsg = '';
	    if ($item->deleted) {
	        return false;
	    }
	    // link_lrd felvitele 'tag'='del' target=$item->id
	    $res = $this->dna_call('add_lrdu',["base" => $item->id, "target" => $item->id, "tag" => "D"]);
		if (isset($res->Ok)) {
		    return true;
		} else {
		    $this->errorMsg = JSON_encode($res);
		    return false;
		}
	}
	
	/**
	* set right in item
	* @param BtreeItem $item
	* @param string $right
	*/
	public function setRight(BtreeItem $item, string $right): bool {
	    $this->errorMsg = '';
	    if ($item->right != '') {
	        $this->errorMsg = 'right_exists';
	        return false;
	    }
	    // link_lrd felvitele 'tag'='right' target=$right
	    $res = $this->dna_call('add_lrdu',["base" =>  $item->id, "target" => $right, "tag" => "R"]);
	    if (isset($res->Ok)) {
	        return true;
	    } else {
	        $this->errorMsg = JSON_encode($res);
	        return false;
	    }
	}

	/**
	* set left in item
	* @param BtreeItem $item
	* @param string $left
	*/
	public function setLeft(BtreeItem $item, string $left): bool {
	    $this->errorMsg = '';
	    if ($item->left != '') {
	        $this->errorMsg = 'left_exists';
	        return false;
	    }
	    // link_lrd felvitele 'tag'='right' target=$right
	    $res = $this->dna_call('add_lrdu', ["base" => $item->id, "target" => $left, "tag" => 'L']);
	    if (isset($res->Ok)) {
	        return true;
	    } else {
	        $this->errorMsg = JSON_encode($res);
	        return false;
	    }
	}

	/**
	* btree empty?
	* @return bool
	*/
	public function isEmpty(): bool {
	    $this->errorMsg = '';
	    return false;	
	}	
	
	/**
	* get root item 
	*/
	public function getRoot(): String {
	    $this->errorMsg = '';
	    $res = $this->dna_call('get_root',[]);
	    if (isset($res->Ok)) {
    	    return $res->Ok;
	    } else {
	        $this->errorMsg = JSON_encode($res);
	        return "";
	    }
	}
	
	/**
	* delete all items
	*/
	public function clear() {
	    // not implemented
	}
	
	public function init() {
	    $this->errorMsg = '';
	    if ($this->name == 'db.colNames') {
	        $res = $this->dna_call('get_root',[]);
	        if (isset($res->Ok)) {
	            $this->rootId = $res->Ok;
	        } else {
	            $this->errorMsg = JSON_encode($res);
	        }
	    } else {
    	    // képezi a root elemet a DHT -ba
    	    $item = new BtreeItem();
    	    $item->value = $this->name.'-btree-root';
    	    $this->rootId = $this->add($item);
    	    $res = $this->dna_call('del_item', $this->rootId);
    	    if (!isset($res->Ok)) {
    	        $this->errorMsg = JSON_encode($res);
    	    }
	    }
	}

	/**
	* dump items to json string
	* @return string
	*/
	public function dump() {
	    // not implemented
	    $this->errorMsg = '';
	}
	
	/**
	* load items from phisical storage
	*/
	public function load() {
	    // not implemented
	    $this->errorMsg = '';
	}
	
	/**
	* save items into phisical storage
	*/
	public function save() {
	    // not implemented
	    $this->errorMsg = '';
	}
	
	public function setPsw(string $psw):bool {
	    $this->errorMsg = '';
	    $res = $this->dna_call('set_psw',["psw" => $psw]);
	    if (!isset($res->Ok)) {
	        $this->errorMsg = JSON_encode($res);
	        return false;
	    } else {
	        $res->rootId = $res;
	        return $res->Ok;
	    }
	}
	
	/**
	 * lock database
	 * @return bool
	 */
	public function lock(): bool {
	    // not implemented
	    $this->errorMsg = '';
	    return true;
	}
	
	/**
	 * unlock database
	 */
	public function unlock() {
	    // not implemented
	    $this->errorMsg = '';
	}

}
?>