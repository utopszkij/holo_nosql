'use strict';

class Indexes {
	public collection;
	public indexes : string[];
	public add(propName: string) : void {
		if (this.propNames.indexOf(propName) < 0) {
			this.propNames.push(propName);
		}
		return;
	}
	public del(propName: string): void {
		var i = this.propNames.indexOf(propName);
		if (i >= 0) {
			this.propNames.splice(i,1);		
		}
		return;
	}
}

interface PropDef {

}

class Validator {
	public collection;
	public requests: string[];
	public propDefs: PropDef[]; 
	public addRequest(propName: string): void {
	}	
	public delRequest(propName: string): void {
	}	
	public addPropdef(propDef: PropDef): void {
	}	
	public delPropDef(propName): void {
	}	
}

interface AccessRight {
	action: string;
	tos: string[];
}

class AccessRights {
	public collection;
	public docId;
	public accessRights : AccessRight[];
	public add(action: string, to: string) : void {
	}
	public del(action: string, to: string): void {
	}
}

class Cursor {
	public collection;
	public hashes: string[];
	public pos: number; 
	public next(): object {
	}
}

class Collection {
	public db;
	public colName: string;
	public accessRight: object; 
	public validator: object; 
	public indexes: object;
	protected findPropName: string;
	protected findPropValue: any;
	public addDocument(doc: object) {
	}
	public deldDocument(docId: string) {
	}
	public updateDocument(doc: object) {
	}
	public find(propName: string, propvalue: any): Cursor {
	}
	public doEmpty() {
	}
	public addDocAccessRights(docId: string, action: string, to: string) {
	}
	public delDocAccessRights(docId: string, action: string, to: string) {
	}
	public getDocAccessRights(docId, computed: boolean): AccessRights {
	}
}

class Database {
	protected userName: string;
	protected psw: string;
	public setUser(username, psw) {
	}
	public createCollection(colName: string): void {
	}
	public dropCollection(colName: string) {
	}
	public collection(colName: string): Collection {
	} 	
}

