<?php

/**
* Document storage in holochain DHT
*
* requested constants: HOLOPSW, HOLOURL, HOLOINSTANCE, HOLOFULLCHAIN
*
* logical DHT-structure:
* ----------------------
* btree_item  {key:"btree-root", value:"btreeName", parent:"", leftId:"", rightId:"", deleted:true}
*         +---link_lrdu---tag='D'
*         +---link_psw----tag='xxxxx'   target=self   only one 
*         +---link_lock---tag='dblock' target=self    only one 
* collection_item   {ty, colName, indexes, indexRoots}   
*         +---link_lrdu---tag='D'|'U'   if tag=='U' then target=actual dcollection' address 
* document_item     {ty, colName, .....} 
*         +---link_lrdu---tag='D'|'U'   if tag=='U' then target=actual document' address 
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
*   root_btree
*       +--(col_name)-------> collection
*                              |      |
*                          btree_f1  btree_f2
*                              |       +----(value)---> document
*                              +------------(value)---> document
*                              
* Logical rekords  (all type store in DHT by "Item")
* ----------------
* btreeItem  {parent, key, value) 
* collection {name, indexNames, indexRoots}  
* document   {colName, data} 
*/

if (!defined('HOLOFULLCHAIN')) {
    define('HOLOFULLCHAIN',false);
}
class HoloDocStorage extends DocStorage {
	public $rootId = '';
	private $errorMsg = '';
		
	function __construct() {
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
	* read document from storage
	* @param string $id
	* @return Document if not found id=''
	*/	
	public function read(string $id): Document  {
	    $this->errorMsg = '';
	    // Entry olvasás a DHT -ből
		$result = new Document();
		$res = $this->dna_call('get_item', ["id" => $id]);
		if (isset($res->Ok)) {
		  $encrypted = JSON_decode($res->Ok->App[1])->datastr;
		  if (substr($encrypted,0,1) != '{') {
		      // nem értem miért:
		      $encrypted = str_replace(' ','+',$encrypted);
		      $decryption_iv = '1201586891011121';
		      $dataStr = openssl_decrypt ($encrypted, "AES-128-CTR", HOLOPSW, 0, $decryption_iv);
		  } else {
		      $dataStr = $encrypted;
		  }
		  $item = JSON_decode($dataStr);
	      foreach ($item as $fn => $fv) {
	          $result->$fn = $fv;
	      }
	      $result->id = $id;
	      $res = $this->dna_call('get_lrdu',["base" => $id]);
	      if (isset($res->Ok)) {
	          $links = $res->Ok->links;
	          foreach ($links as $link)  {
	              if ($link->tag == 'D') {
	                    if (isset($result->previos)) {
	                       $previos = $result->previos;
	                       $result = new Document();
	                       $result->previos = $previos;
	                    } else {
	                        $result = new Document();
	                    }
                        $this->errorMsg = 'deleted';
	              }
	              if ($link->tag == 'U') {
	                  $result = $this->read($link->address);
	                  $result->id = $id;
	              }
	          } // foreach links
	      } else {
	          $result = new Document();
	          $this->errorMsg = JSON_encode($res);
	      } // succes link_lrdu read
		} else {
		    $result = new Document();
		    $this->errorMsg = 'not_found';
		} // succes item read
		return $result;
	}	

	/**
	* storage new Document into storage
	* @param Document
	* @return string  new id
	*/
	public function add(Document $item): string {
	    $this->errorMsg = '';
	    if (HOLOFULLCHAIN) {
    	    // read exists last id, and delete exists link_lrdu tag='last' item
    	    $lastId = '';
    	    $res1 = $this->dna_call('get_lrdu',["base" => $this->rootId]);
    	    if (isset($res1->Ok)) {
    	        $links = $res1->Ok->links;
    	        foreach ($links as $link) {
    	            if ($link->tag = 'last') {
    	                $lastId = $link->address;
    	                $res2 = $this->dna_call('del_lrdu',
    	                    ["base" => $this->rootId, "target" => $link->address, "tag" => $link->tag]);
    	            }
    	        }
    	    }
            $item->previos = $lastId;
	    }
	    
	    // item encrypt
	    $s = JSON_encode($item);
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
	 * delete one Document
	 * @param BtreeItem $item
	 */
	public function remove(Document $item): bool {
	    $this->errorMsg = '';
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
	 * update one Document
	 * @param BtreeItem $item
	 */
	public function update(Document $oldDocument, Document $newDocument): bool {
	    $this->errorMsg = '';
	    $result = true;
	    $res = $this->read($oldDocument->id);
	    if ($res->id == '') {
	        $result = false;
	        $this->errorMsg = 'not_found';
	    } else {
    	    $address = $this->add($newDocument);
    	    //del  exists link_lrdu
    	    $res1 = $this->dna_call('get_lrdu',["base" => $oldDocument->id]);
    	    if (isset($res1->Ok)) {
    	        $links = $res1->Ok->links;
    	        foreach ($links as $link) {
    	            $res2 = $this->dna_call('del_lrdu',
    	                ["base" => $oldDocument->id, "target" => $link->address, "tag" => $link->tag]);
    	            if (!isset($res2->Ok)) {
    	                $this->errorMsg = JSON_encode($res);
    	            }
    	        }
    	    }
    	    // add new link_lrdu 
    	    $res = $this->dna_call('add_lrdu',["base" => $oldDocument->id, "target" => $address, "tag" => "U"]);
	    }
	    return $result;
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
	
	/**
	 * get first item by fullChain
	 * @return Socument
	 */
	public function first(): Socument {
	    $this->errorMsg = '';
	    $this->result = new Document();
	    if (HOLOFULLCHAIN == false) {
	        $this->errorMsg = 'not_full_chain';
	    } else {
    	    $lastId = '';
    	    $res1 = $this->dna_call('get_lrdu',["base" => $this->rootId]);
    	    if (isset($res1->Ok)) {
    	        $links = $res1->Ok->links;
    	        foreach ($links as $link) {
    	            if ($link->tag = 'last') {
    	                $lastId = $link->address;
    	            }
    	        }
    	    }
    	    $result = $this->read($lastId);
	    }
	    return $this->result;
	}
	
	/**
	 * get next item by full chain
	 * @param Document $item
	 * @return Document
	 */
	public function next(Document $item): Document {
	    $result = new Document;
	    $this->errormsg = '';
	    if (!HOLOFULLCHAIN) {
	        $this->errorMsg = 'not_full_chain';
	    } else {
    	    if ($item->previos != '') {
    	        $result = get($item->parent);
    	    } else {
    	        $this->errorMsg = 'eof';
    	    }
	    }
	    return $result;
	}
	

}
?>