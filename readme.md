# Minimal noSQL database in holochain DHT

Planned, under work, currently does not work.

## Overview

MVC_aspp  --  holo_nosql_interface  ---AJAX---  holo_nosql DNA app

## Request softwares

TypeScript, JavaScript, holochain-proto

## Planned holo_nosql_interface
```
var db = new Database(userName, psw);
	db.createCollection(colName, indexes, validator, accessRights, callbackFun);
	db.updateCollection(colName, indexes, validator, accessRights, callbackFun);
	db.dropCollection(colName, callbackFun);
	var col = db.collection(colName);
		col.addDocument(doc, accessRights, callbackFun);
		col.updateDocument(doc,accessRights, callbackFun);
		col.delDocument(doc, callBackFun);
		col.empty(callBackFun);
		var cursor = col.find(propName, propvalue);
			cursor.next(callbackFun);
		
```
## Licence
GNU/GPL

## Author
Tibor Fogler
tibor.fogler@gmail.com

