/**
* DNA app from cat-db
*  root_btree_item  {key:"btree-root", value:"btreeName", parent:"", leftId:"", rightId:"", deleted:true}
*         +---link_lrdu---tag='del'
*         +---link_psw----tag='xxxxx'   target=self   only one 
*         +---link_lock---tag='dblock' target=self  only one
*       Entry : Item  {datastr} tartalom szerint: BtreeIte, Collcetion, Document
*       a root_btree colName alapján collection tipusú item rekordokra mutat
*  must first call set_psw only one!       
*  publikus funkciok
*
*  must edit:         sharing: Sharing::Source,  --> sharing: Sharing::Public
*
*/
#[macro_use]
extern crate hdk;
extern crate serde;
#[macro_use]
extern crate serde_derive;
extern crate serde_json;
#[macro_use]
extern crate holochain_json_derive;

use hdk::{
    entry_definition::ValidatingEntryType,
    error::ZomeApiResult,
};
use hdk::holochain_core_types::{
    entry::Entry,
    dna::entry_types::Sharing,
    link::LinkMatch
};

use hdk::holochain_persistence_api::{
    cas::content::Address,
};

use hdk::holochain_json_api::{
    error::JsonError,
    json::JsonString,
};

use hdk::prelude::GetLinksOptions;
use hdk::prelude::GetLinksResult;

// see https://developer.holochain.org/api/0.0.47-alpha1/hdk/ for info on using the hdk library

// This is a sample zome that defines an entry type "MyEntry" that can be committed to the
// agent's chain via the exposed function create_my_entry

#[derive(Serialize, Deserialize, Debug, DefaultJson,Clone)]
pub struct Item {
    datastr: String,
}
#[derive(Serialize, Deserialize, Debug, DefaultJson,Clone)]
pub struct Param {
    psw: String,
    id: String,
    datastr: String,
    base: String,
    target: String,
    tag: String
}

pub fn handle_set_psw(psw: String) -> ZomeApiResult<bool> {
	let mut result = true;   
   let root = Item {
			datastr: "{\"parent\":\"\", \"key\":\"\", \"value\".\"db.colNames\"}".to_string()
   };
   let entry =  Entry::App("item".into(), root.into());
   let address = hdk::entry_address(&entry)?;
   
   // check set psw exists?
   let links = hdk::get_links(&address,
 			                     LinkMatch::Exactly("link_psw"),
			                     LinkMatch::Any)?;

   if links.addresses().len() > 0 {
   	// exists
   	result = false;
   } else {
		// insert root item
   	let address = hdk::commit_entry(&entry)?;
   	// set it is deleted
   	hdk::link_entries(&address, &address, "link_lrdu", "D")?;
		// insert link_psw
   	hdk::link_entries(&address, &address, "link_psw", &psw)?;
   }	
   Ok(result)
}

pub fn handle_add_item(psw: String, pdatastr: String) -> ZomeApiResult<Address> {
	 let correct_psw = get_correct_psw()?;
	 if psw == correct_psw {		
	 	let item = Item {
			datastr: pdatastr
	 	};
    	let entry = Entry::App("item".into(), item.into());
    	let address = hdk::commit_entry(&entry)?;
    	Ok(address)
    } else {
      generate_error("incorrect_password".to_string())?;
   	Ok("psw_error".into())
    }
}

pub fn handle_get_item(psw: String, id: String) -> ZomeApiResult<Option<Entry>> {
	 let correct_psw = get_correct_psw()?;
	 if psw == correct_psw {		
	 	hdk::get_entry(&id.into())
    } else {
    	// generate error 
      generate_error("incorrect_password".to_string())?;
	 	hdk::get_entry(&id.into())
    }
}

pub fn handle_get_lrdu(psw: String, base: String) -> ZomeApiResult<GetLinksResult> {
	 let correct_psw = get_correct_psw()?;
	 if psw == correct_psw {		
		let mut get_links_options = GetLinksOptions::default();
   	get_links_options.headers = true;
   	hdk::get_links_with_options(&base.into(),
 			                  LinkMatch::Exactly("link_lrdu"),
			                  LinkMatch::Any,
			                  get_links_options)
    } else {
    	// generate error 
      generate_error("incorrect_password".to_string())?;
		let mut get_links_options = GetLinksOptions::default();
   	get_links_options.headers = true;
   	hdk::get_links_with_options(&base.into(),
 			                  LinkMatch::Exactly("link_lrdu"),
			                  LinkMatch::Any,
			                  get_links_options)
    }
}

pub fn handle_add_lrdu(psw: String, base: String, target: String, tag: String) -> ZomeApiResult<bool> {
	 let correct_psw = get_correct_psw()?;
	 if psw == correct_psw {		
		hdk::link_entries(&base.into(), &target.into(), "link_lrdu", &tag)?;
		Ok(true)
    } else {
    	// generate error 
      generate_error("incorrect_password".to_string())?;
      Ok(false)
    }

}

pub fn handle_del_lrdu(psw: String, base: String, target: String, tag: String) -> ZomeApiResult<bool> {
	 let correct_psw = get_correct_psw()?;
	 if psw == correct_psw {		
		hdk::remove_link(&base.into(), &target.into(), "link_lrdu", &tag)?;
		Ok(true)
    } else {
    	// generate error 
      generate_error("incorrect_password".to_string())?;
      Ok(false)
    }

}

pub fn handle_get_root(_psw: String) -> ZomeApiResult<Address> {
   let root = Item {
			datastr: "{\"parent\":\"\", \"key\":\"\", \"value\".\"db.colNames\"}".to_string()
   };
   let entry =  Entry::App("item".into(), root.into());
   let address = hdk::entry_address(&entry)?;
   Ok(address)
}

fn generate_error(str: String) -> ZomeApiResult<bool> {
	// calculate root address
   let root = Item {
			datastr: "{\"parent\":\"\", \"key\":\"\", \"value\".\"db.colNames\"}".to_string()
   };
   let entry =  Entry::App("item".into(), root.into());
   let address = hdk::entry_address(&entry)?;
   // try get not exists link
   hdk::get_links(&address,
 	               LinkMatch::Exactly(&str),
	               LinkMatch::Any)?;
	Ok(false)
}

fn get_correct_psw() -> ZomeApiResult<String> {
	
	// calculate root address
   let root = Item {
			datastr: "{\"parent\":\"\", \"key\":\"\", \"value\".\"db.colNames\"}".to_string()
   };
   let entry =  Entry::App("item".into(), root.into());
   let address = hdk::entry_address(&entry)?;
   
   // get from link_psw
	let mut get_links_options = GetLinksOptions::default();
   get_links_options.headers = true;
   let links = hdk::get_links_with_options(&address,
 			                  LinkMatch::Exactly("link_psw"),
			                  LinkMatch::Any,
			                  get_links_options)?;
   if links.tags().len() > 0 {
		Ok(links.tags()[0].clone())   
   } else {
      generate_error("error_in_get_correct_psw".to_string())?;
		Ok("nmnjkdfjuztr".to_string())
   }			   
}

fn definition() -> ValidatingEntryType {
    entry!(
        name: "item",
        description: "BtreeItem or Collection oe Document or Transactionlog",
        sharing: Sharing::Public,
        validation_package: || {
            hdk::ValidationPackageDefinition::Entry
        },

        validation: | _validation_data: hdk::EntryValidationData<Item>| {
            Ok(())
        },
        links: [
                from!(
                   "item",
                   link_type: "link_psw",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
 			  				  Ok(())
						 }
				    ),
                from!(
                   "item",
                   link_type: "link_lrdu",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
					  			Ok(())
						 }
				    ),
                from!(
                   "item",
                   link_type: "link_lock",
                   validation_package: || {
                       hdk::ValidationPackageDefinition::Entry
                   },
                   validation: | _validation_data: hdk::LinkValidationData| {
					  			Ok(())
						 }
				    )
				]    
    )
}

define_zome! {
    entries: [
       definition()
    ]
    init: || { Ok(()) }
    validate_agent: |validation_data : EntryValidationData::<AgentId>| {
        Ok(())
    }
    functions: [
        set_psw: {
            inputs: |psw: String|,
            outputs: |result: ZomeApiResult<bool>|,
            handler: handle_set_psw
        }
        get_root: {
				inputs: |psw: String|,
				outputs: |result: ZomeApiResult<Address>|,
				handler: handle_get_root        
        } 
        add_item: {
            inputs: |psw: String, pdatastr: String|,
            outputs: |result: ZomeApiResult<Address>|,
            handler: handle_add_item
        }
        get_item: {
            inputs: |psw: String, id: String|,
            outputs: |result: ZomeApiResult<Option<Entry>>|,
            handler: handle_get_item
        }
        get_lrdu: {
				inputs: |psw: String, base: String|,
				outputs: |result: ZomeApiResult<GetLinksResult>|,
				handler: handle_get_lrdu        
        } 
        add_lrdu: {
				inputs: |psw: String, base: String, target: String, tag: String|,
				outputs: |result: ZomeApiResult<bool>|,
				handler: handle_add_lrdu        
        } 
        del_lrdu: {
				inputs: |psw: String, base: String, target: String, tag: String|,
				outputs: |result: ZomeApiResult<bool>|,
				handler: handle_del_lrdu        
        } 
    ]

    traits: {
        hc_public [set_psw, add_item, get_item, get_lrdu, add_lrdu, del_lrdu, get_root]
    }
}
