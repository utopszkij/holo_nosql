#![feature(proc_macro_hygiene)]
/**
* struct-db noSQL database
*
* data storage konception
*
*       root_collection                |
*         +--link_name--> collection
*                           +--link_inddef
*                           +--link_idmax
*                           +--link_id-->     document
*                           +--link_unique--> document
*                           +--link_ind1-->   document
*                           +--link_ind2-->   document
*                           +--link_ind3-->   document
* document.data: fieldName,valueStr,fieldName,valueStr.....
*    not "," char in valueStr (replace it "&#44;")
* link_name
*    base: collection, address: collection, tag: collection.name
* link_inddef
*   base: collection, address: collection,
*   tag:  field_name,fieldName,fieldName,... max. 4 item,
*         first item is unique
* link_idmax use by collectionLock, in the normal situation there is only one element
*   base:root, address: root, tag: current idmax
* link_id        base: collection, address: document, tag: id
* link_unique    base: collection, address: document, tag: document.fieldValueStr
* link_ind1      base: collectiom, address: document, tag: document.fieldValueStr
* link_ind2      base: collectiom, address: document, tag: document.fieldValueStr
* link_ind2      base: collectiom, address: document, tag: document.fieldValueStr
*
* rules: must one "id" field it is unique autoinc
*        max. one unique index,
*        max tree simple index
*/
#[macro_use]
extern crate hdk;
extern crate hdk_proc_macros;
extern crate serde;
#[macro_use]
extern crate serde_derive;
extern crate serde_json;
#[macro_use]
extern crate holochain_json_derive;

use hdk::prelude::GetLinksOptions;
use hdk::prelude::GetLinksResultCount;
use hdk::prelude::Pagination;
use hdk::prelude::SizePagination;
use hdk::LinkValidationData::LinkAdd;

use hdk::{
    entry_definition::ValidatingEntryType,
    error::ZomeApiResult,
};

use hdk::holochain_core_types::{
    entry::Entry,
    dna::entry_types::Sharing,
    link::LinkMatch,
    link::LinkActionKind,
};

use hdk::holochain_json_api::{
    json::JsonString,
    error::JsonError,
};

use hdk::holochain_persistence_api::{
    cas::content::Address
};

use hdk_proc_macros::zome;

#[derive(Serialize, Deserialize, Debug, DefaultJson, Clone)]
pub struct Collection {
	orig_name: String  // this is orig name, not change if udate collection.
}

#[derive(Serialize, Deserialize, Debug, DefaultJson, Clone)]
pub struct Document {
	 id: String,
	 collection: Address, // collection address
    data: Vec<String>,  // [name, value, name, value,...] must in indexfields
}

#[zome]
mod database {

	 /**
	 * init DNA
    * create root collection
	 */
    #[init]
    fn init() {
      let root = Collection {
			orig_name: "root-collection".to_string()
	   };
	   let entry =  Entry::App("collection".into(), root.into());
	   hdk::commit_entry(&entry)?;
		Ok(())
    }

    #[validate_agent]
    pub fn validate_agent(validation_data: EntryValidationData<AgentId>) {
        Ok(())
    }


    #[entry_def]
    fn collection_entry_def() -> ValidatingEntryType {
        entry!(
            name: "collection",
            description: "Collection entry",
            sharing: Sharing::Public,
            validation_package: || {
                hdk::ValidationPackageDefinition::Entry
            },
            validation: | validation_data: hdk::EntryValidationData<Collection>| {
					if collection_validation(validation_data)? {
               	Ok(())
               } else {
						Err("not_unique3".into())
               }
            },
            links: [
                from!(
                   "collection",
                   link_type: "link_idmax",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | validation_data: hdk::LinkValidationData| {
					  			if idmax_link_validation(validation_data)? {
					  				Ok(())
					  			} else {
					  			  Err("dblock3".into())
					  			}
						 }
				    ),
                from!(
                   "collection",
                   link_type: "link_name",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | validation_data: hdk::LinkValidationData| {
					  			if name_link_validation(validation_data)? {
					  				Ok(())
					  			} else {
					  			  Err("not_unique1".into())
					  			}
						 }
				    ),
                from!(
                   "collection",
                   link_type: "link_inddef",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
					  			Ok(())
						 }
				    )
            ] // links
        ) // entry
    } // fn

    #[entry_def]
    fn document_entry_def() -> ValidatingEntryType {
        entry!(
            name: "document",
            description: "Collection entry",
            sharing: Sharing::Public,
            validation_package: || {
                hdk::ValidationPackageDefinition::Entry
            },
            validation: | validation_data: hdk::EntryValidationData<Document>| {
               if document_validation(validation_data)? {
               	Ok(())
               } else {
               	Err("wrong_document".into())
               }
            },
            links: [
                // index by document id unique string see "1", "2" ....
                from!(
                   "collection",
                   link_type: "link_id",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: |_validation_data: hdk::LinkValidationData| {
                       Ok(())
                   }
                ),
                from!(
                   "collection",
                   link_type: "link_unique",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | validation_data: hdk::LinkValidationData| {
					  			if unique_link_validation(validation_data)? {
					  				Ok(())
					  			} else {
					  			  Err("not_unique2".into())
					  			}
						 }
				    ),
                from!(
                   "collection",
                   link_type: "link_ind1",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
				  			  Ok(())
						 }
				    ),
                from!(
                   "collection",
                   link_type: "link_ind2",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
				  			  Ok(())
						 }
				    ),
                from!(
                   "collection",
                   link_type: "link_ind3",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
				  			  Ok(())
						 }
				    )
            ] // links
        )
    } // fn

    /**
    * link validation for document' link_idmax
    * - must only max one old link item
    * - if there is one old then must old.tag + 1 == new.tag
    * - if not old then must new.tag == 1
    */
    fn idmax_link_validation(validation_data: hdk::LinkValidationData) -> ZomeApiResult<bool> {
      match validation_data {
      	LinkAdd {link, ..} => {
      		if link.action_kind == LinkActionKind::ADD {
      				// current link:  link.lin_type, link.link.base(),
      				//         link.link.target(), link.link.tag()
		      		if link.link.link_type() == "link_idmax" {
				         let tags = get_link_tags(link.link.base(), "link_idmax".into())?;
		      			if tags.len() == 1 {
		      				// let old_tag:u64 = tags[0].clone().parse().unwrap();
		      				let old_tag:u64 = string_to_u64(&tags[0]);
		      				let new_tag:u64 = string_to_u64(link.link.tag());
		      				if new_tag == old_tag + 1 {
		      					Ok(true)
		      				} else {
		      					Ok(false)
		      				}
		      			} else if tags.len() == 0 {
		      				let new_tag:u64 = string_to_u64(link.link.tag());
		      				if new_tag == 1 {
		      					Ok(true)
		      				} else {
		      					Ok(false)
		      				}
		      			} else {
		      				Ok(false)
		      			}
		      		} else {
		      			// not "link_idmax" link
		      			Ok(true)
		      		}
     			} else {
     				// not ADD link_action
     				Ok(true)
     			}
      	},
      	// not LinkAdd
      	_ => Ok(true)
      }
    }

   /**
    * link validation for link_unique
    */
    fn unique_link_validation(validation_data: hdk::LinkValidationData) -> ZomeApiResult<bool> {
      match validation_data {
      	LinkAdd {link, ..} => {
      		if link.action_kind == LinkActionKind::ADD {
      				// current link data:  link.link_type, link.link.base(),
      				//         link.link.target(), link.link.tag()
		      		if link.link.link_type() == "link_unique" {
		      			let res = hdk::get_links(&link.link.base(),
		      								     LinkMatch::Exactly("link_unique"),
		      								     LinkMatch::Exactly(&link.link.tag()))?;
		      			if res.addresses().len() > 0 {
	      					Ok(false)
		      			} else {
		      				Ok(true)
		      			}
		      		} else {
	      				Ok(true)
		      		}
     			} else {
     				// not ADD link_action
     				Ok(true)
     			}
      	},
      	// not LinkAdd
      	_ => Ok(true)
      }
    }


   /**
    * link validation for link_name
    */
    fn name_link_validation(validation_data: hdk::LinkValidationData) -> ZomeApiResult<bool> {
      match validation_data {
      	LinkAdd {link, ..} => {
      		if link.action_kind == LinkActionKind::ADD {
      				// current link data:  link.lin_type, link.link.base(),
      				//         link.link.target(), link.link.tag()
		      		if link.link.link_type() == "link_name" {
		      			let res = hdk::get_links(&link.link.base(),
		      								 LinkMatch::Exactly("link_name"),
		      								 LinkMatch::Exactly(&link.link.tag()))?;
		      			if res.links().len() > 0 {
	      					Ok(false)
		      			} else {
		      				Ok(true)
		      			}
		      		} else {
	      				Ok(true)
		      		}
     			} else {
     				// not ADD link_action
     				Ok(true)
     			}
      	},
      	// not LinkAdd
      	_ => Ok(true)
      }
    }

 	 /**
 	 * collection entry validator - name is unique?
 	 */
 	 fn collection_validation(validation_data: hdk::EntryValidationData<Collection>) -> ZomeApiResult<bool> {
	 	match validation_data {
	      hdk::EntryValidationData::Create{ entry, .. } => {
		      let root = Collection {
					orig_name: "root-collection".to_string()
			   };
			   let root_entry = Entry::App("collection".into(), root.into());
			   let root_address = hdk::entry_address(&root_entry)?;

		      let res = hdk::get_links(&root_address,
		      								 LinkMatch::Exactly("link_name"),
		      								 LinkMatch::Exactly(&entry.orig_name))?;
			   if res.links().len() == 0 {
					Ok(true)
			   } else {
					Ok(false)
			   }
         },
         _ => Ok(true)
      } // match
 	 }

	 /**
	 * document entry validator   -- get unique_filed_name for iddef,
	 * check unique_field_name' value  is unique?
	 */
 	 fn document_validation(validation_data: hdk::EntryValidationData<Document>) -> ZomeApiResult<bool> {
	 	match validation_data {
         hdk::EntryValidationData::Create{ entry, .. } => {
         	let mut result = true;
				// get inddefs for collection
		      let inddefs = get_link_tags(&entry.collection, "link_inddef".into())?;
		      if inddefs.len() > 0 {
		 	 		for inddef in inddefs {
		 	 			if inddef[0..1] == "*".to_string() {
							let mut value = "".to_string();
							let mut i = 0;
							while i < entry.data.len() {
								if entry.data[i] == inddef[1..] {
									let j = i + 1;
									value = entry.data[j].clone();
								}
								i = i + 2;
							}
		      			let res = hdk::get_links(&entry.collection,
		      										    LinkMatch::Exactly("link_unique"),
		      										    LinkMatch::Exactly(&value))?;
							if res.links().len() > 0 {
								result = false;
							}
		 	 			} // first char is "*"
		 	 		} // for
		 	 	}
		 	 	Ok(result)
         }, // matck  =>
         _ => Ok(true)
      } // match
 	 } // fn

	 /**
	 * add new collection, create idmax and inddef link
	 */
    #[zome_fn("hc_public")]
    pub fn create_collection(name: String, inddefs: String) -> ZomeApiResult<Address> {
      let root = Collection {
			orig_name: "root-collection".to_string()
	   };
	   let root_entry = Entry::App("collection".into(), root.into());
	   let root_address = hdk::entry_address(&root_entry)?;

      let collection = Collection {
          orig_name: name.clone()
      };
      let entry = Entry::App("collection".into(), collection.into());
      let address = hdk::commit_entry(&entry)?;
      hdk::link_entries(&root_address, &address, "link_name", &name)?;
      hdk::link_entries(&address, &address, "link_inddef", &inddefs)?;
		Ok(address)
    }

	 /**
	 * getcollection address by name
	 * if not found return ""
	 */
    #[zome_fn("hc_public")]
    pub fn get_collection_address(name: String) -> ZomeApiResult<Address> {
	    let root = Collection {
			orig_name: "root-collection".to_string()
		};
		let root_entry = Entry::App("collection".into(), root.into());
		let root_address = hdk::entry_address(&root_entry)?;
		let addresses = get_link_addresses(&root_address, "link_name".into(), name)?;
		if addresses.len() > 0 {
	  	   	Ok(addresses[0].clone())
  	   } else {
			Ok("".into())
  	   }
    }

	 /**
	 * rename collection
	 * if not found return ""
	 */
    #[zome_fn("hc_public")]
    pub fn rename_collection(old_name: String, new_name: String) -> ZomeApiResult<Address> {
       let root = Collection {
			orig_name: "root-collection".to_string()
	   };
	   let root_entry = Entry::App("collection".into(), root.into());
	   let root_address = hdk::entry_address(&root_entry)?;
	   let addresses = get_link_addresses(&root_address, "link_name".into(), old_name.clone())?;
	   if addresses.len() > 0 {
			let address = addresses[0].clone();
	        // check exists old link entry? --- not remove this code!
 			let links = hdk::get_links(&root_address,
 			                     LinkMatch::Exactly("link_name"),
			                     LinkMatch::Exactly(&old_name))?;
			// remove old link entry
			if links.addresses().len() > 0 {
				hdk::remove_link(&root_address, &links.addresses()[0], "link_name", &old_name)?;
			}
			// add new link entry
		    hdk::link_entries(&root_address, &address, "link_name", &new_name)?;
	  	   	Ok(address)
  	   } else {
			Ok("error1".into())
  	   }
    }

	 /**
	 * drop collection
	 */
    #[zome_fn("hc_public")]
    pub fn drop_collection(name: String) -> ZomeApiResult<bool> {
      let root = Collection {
			orig_name: "root-collection".to_string()
	   };
	   let root_entry = Entry::App("collection".into(), root.into());
		let root_address = hdk::entry_address(&root_entry)?;
		let addresses = get_link_addresses(&root_address, "link_name".into(), name.clone())?;
		if addresses.len() > 0 {
			let address = addresses[0].clone();
			hdk::remove_link(&root_address, &address, "link_name", &name)?;
  	   	Ok(true)
  	   } else {
			Ok(false)
  	   }
    }

	 /**
	 * add new document and all link
    * if there is dblock not add document, return "dblock" error
	 */
    #[zome_fn("hc_public")]
    pub fn add_document(col_address: Address, data_str: String) -> ZomeApiResult<String> {
			let data = string_to_vec(data_str);
 	     	let sid = make_new_id(&col_address)?;
			if sid == "dblock1" {
				Ok(sid)
			} else {
	         let document = Document {
	            id: sid.clone(),
	            collection: col_address.clone(),
	            data: data.clone()
	         };
	         let entry = Entry::App("document".into(), document.into());
	         let address = hdk::commit_entry(&entry)?;

	         // store "link_id" link by "sid"
	         hdk::link_entries(&col_address, &address, "link_id", &sid)?;
	         add_to_indexes(&col_address, &address, &data)?;
	    	   Ok(sid)
			}
    }

    /**
    * get document by id
    * result {Ok:[{OK:{App:["document":"jsonstr"]}}]}
    *           jsonstr: '{"id":"...", "collection":"...", data:["name","value",...]}'
    * if not found result {Ok:[]}
    */
    #[zome_fn("hc_public")]
    pub fn get_document(col_address: Address, id : String) -> ZomeApiResult<Vec<ZomeApiResult<Entry>>> {
		hdk::get_links_and_load(
		    &col_address,
          LinkMatch::Exactly("link_id"),
          LinkMatch::Exactly(&id),
		)
    }

	 /**
	 * delete document and all link
	 */
    #[zome_fn("hc_public")]
    pub fn delete_document(col_address: Address,
    								id: String,
    								data_str: String) -> ZomeApiResult<bool> {
			let data = string_to_vec(data_str);
			let links = hdk::get_links(
		    	&col_address,
          	LinkMatch::Exactly("link_id"),
          	LinkMatch::Exactly(&id)
			)?;
			if links.addresses().len() > 0 {
			   let address = links.addresses()[0].clone();
		 	   let links = hdk::get_links(&col_address,
 			                  LinkMatch::Exactly("link_id"),
			                  LinkMatch::Exactly(&id))?;
				if links.addresses().len() > 0 {
				   hdk::remove_link(&col_address, &address, "link_id", &id)?;
				}
			    remove_from_indexes(&col_address, &address, &data)?;
			}
    	    Ok(true)
    }

	 /**
	 * update document and all link, if value == "Any" then get all item
	 */
    #[zome_fn("hc_public")]
    pub fn update_document(col_address: Address,
    								id: String,
    								old_data_str: String,
    								new_data_str: String) -> ZomeApiResult<bool> {
			let old_data = string_to_vec(old_data_str);
			let new_data = string_to_vec(new_data_str);
			let links = hdk::get_links(
		    	&col_address,
	          	LinkMatch::Exactly("link_id"),
	          	LinkMatch::Exactly(&id)
			)?;
			if links.addresses().len() > 0 {
			   let old_address = links.addresses()[0].clone();
 			   let links = hdk::get_links(&col_address,
 			                  LinkMatch::Exactly("link_id"),
				              LinkMatch::Exactly(&id))?;
 			   if links.addresses().len() > 0 {
				   hdk::remove_link(&col_address, &old_address, "link_id", &id)?;
 			   }

	           let document = Document {
	             id: id.clone(),
	             collection: col_address.clone(),
	             data: new_data.clone()
	           };
	           let entry = Entry::App("document".into(), document.into());
	           let new_address = hdk::commit_entry(&entry)?;
	           hdk::link_entries(&col_address, &new_address, "link_id", &id)?;
		       if update_indexes(&col_address, &old_address, &new_address, &old_data, &new_data)? {
		    	   Ok(true)
		       } else {
			       Ok(false)
		       }
		   } else {
			   Ok(false)
		   }
    }

    /**
    * get document by address
    * if not found return {Ok:null}
    * if found return: {Ok:{App:["documentName","jsonstr"]}}
    */
    #[zome_fn("hc_public")]
    pub fn get_document_by_address(address: Address) -> ZomeApiResult<Option<Entry>> {
		hdk::get_entry(&address)
    }

	 /**
	 * get documents adresses from collection, by pagination
	 */
    #[zome_fn("hc_public")]
	 pub fn get_documents(col_address: Address,
						  field_name: String,
						  value: String,
	                      page_number: usize,
	                      page_size: usize) -> ZomeApiResult<Vec<Address>> {

	  // build link_options
      let mut get_links_options = GetLinksOptions::default();
      get_links_options.pagination = Option::from(Pagination::Size(SizePagination{page_number, page_size}));

      // build link_neme
      let mut link_name = "not-ind";
	  if field_name == "id".to_string() {
	  	link_name = "link_id"
	  } else {
 	 	let tags = get_link_tags(&col_address, "link_inddef".to_string())?;
 	 	if tags.len() > 0 {
			let inddefs = string_to_vec(tags[0].clone());
			let mut i = 0;
			for inddef in inddefs {
				if inddef == field_name {
					if i == 0 {
						link_name = "link_unique";
					}
					if i == 1 {
						link_name = "link_ind1";
					}
					if i == 2 {
						link_name = "link_ind2";
					}
					if i == 3 {
						link_name = "link_ind3";
					}
				}
				i = i + 1;
			}
 	 	}
	  }

	  // load from DHT
	  if value == "Any" {
		  let links = hdk::get_links_with_options(
			    &col_address,
	          LinkMatch::Exactly(link_name),
	          LinkMatch::Any,
			  get_links_options,
		  )?;
	      Ok(links.addresses())
	  } else {
		  let links = hdk::get_links_with_options(
			    &col_address,
	          LinkMatch::Exactly(link_name),
	          LinkMatch::Exactly(&value),
			  get_links_options,
		  )?;
	      Ok(links.addresses())
	  }
	 }

	 /**
	 * get document count from collection
	 * return {Ok:{count: num}}
	 */
    #[zome_fn("hc_public")]
	 pub fn get_document_count(col_address: Address) -> ZomeApiResult<GetLinksResultCount> {
        hdk::get_links_count(
            &col_address,
            LinkMatch::Exactly("link_id"),
            LinkMatch::Any,
        )
	 }

	 /**
	 * string to integer konverzió
	 */
    fn string_to_u64(s: &String) -> u64 {
    	s.parse().unwrap()
    }

	 /**
	 * string to Vac<String> conversion
	 * separator: ","
	 */
	 fn string_to_vec(str: String) -> Vec<String> {
    	str.split(",").map(|s| s.to_string()).collect()
	 }

    /**
    * get tags vector from link
    */
    fn get_link_tags(base: &Address, link_type: String) -> ZomeApiResult<Vec<String>> {
	     	let mut get_links_options = GetLinksOptions::default();
   	  	get_links_options.headers = true;
        	let links = hdk::get_links_with_options(&base,
				                                     LinkMatch::Exactly(&link_type),
				                                     LinkMatch::Any,
														       get_links_options,
				                                   )?;
			Ok(links.tags())
    }

    /**
    * get Adress vector from link
    */
    fn get_link_addresses(base: &Address, link_type: String, tag: String) -> ZomeApiResult<Vec<Address>> {
 			let links = hdk::get_links(&base,
 			                     LinkMatch::Exactly(&link_type),
				                 LinkMatch::Exactly(&tag))?;
			Ok(links.addresses())
    }

    /**
    * make new id and store into link_idmax link
    */
    fn make_new_id(col_address: &Address) -> ZomeApiResult<String> {
    		let tags = get_link_tags(&col_address, "link_idmax".to_string())?;
     		let mut sid: String = "dblock2".to_string();
         if tags.len() == 0 {
        		sid = "1".into();
	        	hdk::link_entries(&col_address, &col_address, "link_idmax", &sid)?;
         } else if tags.len() == 1 {
            // there is not dblock
        		let slink_idmax = tags[0].clone();
	  		  	let link_idmax:u64 = string_to_u64(&slink_idmax);
			  	let id =  link_idmax + 1;
			  	sid = id.to_string();
	        	hdk::link_entries(&col_address, &col_address, "link_idmax", &sid)?;
 			    let links = hdk::get_links(&col_address,
 			                   LinkMatch::Exactly("link_idmax"),
				               LinkMatch::Exactly(&slink_idmax))?;
 			    if links.addresses().len() > 0 {
				  	hdk::remove_link(&col_address, &col_address, "link_idmax", &slink_idmax)?;
 			    }
        	}
       	Ok(sid)
    }

 	 /**
 	 * get field value from data by field_name
 	 */
 	 fn get_field_value(data: &Vec<String>, field_name: &String) -> String {
		let mut i = 0;
		let mut result = "".to_string();
		while i < data.len() {
			if data[i] == field_name.to_string() {
				let j = i + 1;
				result = data[j].clone();
			}
			i = i + 1;
		}
		result
 	 }


 	 /**
 	 * add links into unique, ind1, ind2, ind3
 	 */
 	 fn add_to_indexes(col_address: &Address,
 	 						 address: &Address,
 	 						 data: &Vec<String>
 	 						 ) -> ZomeApiResult<bool> {

 	 	// get inddefs from collection' inddef link
 	 	let tags = get_link_tags(&col_address, "link_inddef".to_string())?;
 	 	if tags.len() > 0 {
			let inddefs = string_to_vec(tags[0].clone());
			let mut i = 0;
	 	 	for inddef in inddefs {
	 	 		if inddef != "".to_string() {
					let value = get_field_value(&data, &inddef);
	 	 			if i == 0 {
			         hdk::link_entries(&col_address, &address, "link_unique", &value)?;
	 	 			}
		 	 		if i == 1 {
				      hdk::link_entries(&col_address, &address, "link_ind1", &value)?;
		 	 		}
		 	 		if i == 2 {
				      hdk::link_entries(&col_address, &address, "link_ind2", &value)?;
		 	 		}
		 	 		if i == 3 {
				      hdk::link_entries(&col_address, &address, "link_ind3", &value)?;
		 	 		}
	 	 		} //if
	 	 		i = i + 1;
	 	 	} // for
	 	} // tags.len()
 	 	Ok(true)
 	 }

 	 /**
 	 * remove links into unique, ind1, ind2, ind3
 	 */
 	 fn remove_from_indexes(col_address: &Address,
 	 						 address: &Address,
 	 						 data: &Vec<String>,
 	 						 ) -> ZomeApiResult<bool> {

 	 	// get inddefs from collection' inddef link
 	 	let tags = get_link_tags(&col_address, "link_inddef".to_string())?;
 	 	if tags.len() > 0 {
			let inddefs = string_to_vec(tags[0].clone());
			let mut i = 0;
	 	 	for inddef in inddefs {
	 	 		if inddef != "".to_string() {
					let value = get_field_value(&data, &inddef);
	 	 			if i == 0 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_unique"),
				                     LinkMatch::Exactly(&value))?;
		 			  if links.addresses().len() > 0 {
				          hdk::remove_link(&col_address, &address, "link_unique", &value)?;
		 			  }
	 	 			}
		 	 		if i == 1 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_ind1"),
				                     LinkMatch::Exactly(&value))?;
		 			  if links.addresses().len() > 0 {
					      hdk::remove_link(&col_address, &address, "link_ind1", &value)?;
		 			  }
		 	 		}
		 	 		if i == 2 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_ind2"),
				                     LinkMatch::Exactly(&value))?;
		 			  if links.addresses().len() > 0 {
					      hdk::remove_link(&col_address, &address, "link_ind2", &value)?;
		 			  }
		 	 		}
		 	 		if i == 3 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_ind3"),
				                     LinkMatch::Exactly(&value))?;
		 			  if links.addresses().len() > 0 {
					      hdk::remove_link(&col_address, &address, "link_ind3", &value)?;
		 			  }
		 	 		}
	 	 		} //if
	 	 		i = i + 1;
	 	 	} // for
	 	} // tags.len()
 	 	Ok(true)
 	 }

 	 /**
 	 * update links into unique, ind1, ind2, ind3
 	 */
 	 fn update_indexes(col_address: &Address,
				 	   old_address: &Address,
 	 				   new_address: &Address,
 	 				   old_data: &Vec<String>,
 	 				   new_data: &Vec<String>,
 	 				  ) -> ZomeApiResult<bool> {

 	 	// get inddefs from collection' inddef link
 	 	let tags = get_link_tags(&col_address, "link_inddef".to_string())?;
 	 	if tags.len() > 0 {
			let inddefs = string_to_vec(tags[0].clone());
			let mut i = 0;
	 	 	for inddef in inddefs.clone() {
	 	 		if inddef != "".to_string() {
					let old_value = get_field_value(&old_data, &inddef);
	 	 			if i == 0 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_unique"),
				                     LinkMatch::Exactly(&old_value))?;
		 			  if links.addresses().len() > 0 {
				          hdk::remove_link(&col_address, &old_address, "link_unique", &old_value)?;
		 			  }
	 	 			}
		 	 		if i == 1 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_ind1"),
				                     LinkMatch::Exactly(&old_value))?;
		 			  if links.addresses().len() > 0 {
					      hdk::remove_link(&col_address, &old_address, "link_ind1", &old_value)?;
		 			  }
		 	 		}
		 	 		if i == 2 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_ind2"),
				                     LinkMatch::Exactly(&old_value))?;
		 			  if links.addresses().len() > 0 {
					      hdk::remove_link(&col_address, &old_address, "link_ind2", &old_value)?;
		 			  }
		 	 		}
		 	 		if i == 3 {
		 			  let links = hdk::get_links(&col_address,
 			                         LinkMatch::Exactly("link_ind3"),
				                     LinkMatch::Exactly(&old_value))?;
		 			  if links.addresses().len() > 0 {
					      hdk::remove_link(&col_address, &old_address, "link_ind3", &old_value)?;
		 			  }
		 	 		}
	 	 		} //if
	 	 		i = i + 1;
	 	 	} // for
			i = 0;
	 	 	for inddef in inddefs {
	 	 		if inddef != "".to_string() {
					let new_value = get_field_value(&new_data, &inddef);
	 	 			if i == 0 {
			          hdk::link_entries(&col_address, &new_address, "link_unique", &new_value)?;
	 	 			}
		 	 		if i == 1 {
			          hdk::link_entries(&col_address, &new_address, "link_ind1", &new_value)?;
		 	 		}
		 	 		if i == 2 {
			          hdk::link_entries(&col_address, &new_address, "link_ind2", &new_value)?;
		 	 		}
		 	 		if i == 3 {
			          hdk::link_entries(&col_address, &new_address, "link_ind3", &new_value)?;
		 	 		}
	 	 		} //if
	 	 		i = i + 1;
	 	 	} // for
	 	} // tags.len()
 	 	Ok(true)
 	 }


// ----------------------------------------------------------------------------------
/**

	get_inddefs(name) -> Vec<String>

	create_index(col_address, field_name, unique: bool) -> bool

	drop_index(col_address, field_name)


*/


    /**
    * get AGENT_ADDRESS
    */
    #[zome_fn("hc_public")]
    pub fn get_agent_id() -> ZomeApiResult<Address> {
        Ok(hdk::AGENT_ADDRESS.clone())
    }

    /*
    * get DNA_ADDRESS
    */
    #[zome_fn("hc_public")]
    pub fn get_dna_id() -> ZomeApiResult<Address> {
        Ok(hdk::DNA_ADDRESS.clone())
    }

    /**
    * get root document address
    */
    #[zome_fn("hc_public")]
    pub fn get_root_address() -> ZomeApiResult<Address> {
      let root = Collection {
			orig_name: "root-collection".to_string()
	   };
	   let entry = Entry::App("collection".into(), root.into());
	   hdk::entry_address(&entry)
    }

    /**
    * test_init - use only for unittest!
    */
    #[zome_fn("hc_public")]
    pub fn test_init() -> ZomeApiResult<Address> {
      let root = Collection {
			orig_name: "root-collection".to_string()
	   };
	   let entry = Entry::App("collection".into(), root.into());
	   let entry = hdk::commit_entry(&entry)?;
	   Ok(entry)
    }

}
