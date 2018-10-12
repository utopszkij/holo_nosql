'use strict';
/*****************************************************************************
* minimal noSQL database holochain DNA application
*
* Licence: GNU/GPL
* Author: Tibor Fogler
* Author-email: tibor.fogler@gmail.com
*
* Puplic functions input/output is json string (-- call from MVC web app)
* -----------------------------------------------------------------------
*   addDocument({doc,accessRights?,validator?,indexes?,user}) : {sate, doc}
*   delDocument({doc,user}) {sate}
*   updateDocument({doc,accessRights?,validator?,indexes?,user}) : {state, doc}
*   doFind({cname, propName, propValue,user}) : {state, hashes, pos}
*   doNext({hashes, pos}) : {state,doc,accessRights?,validator?,indexes?,hashes,pos}
*   first({cname, propName, propValue,user}) : {state,doc,accessRights?,validator?,indexes?,hashes,pos}
*   doEmpty({cName,user}) : {state}
*     -- user: '<userName>.<psw>'
*     -- state: 'OK' | 'EOF' | errorMsg
*     -- can you define special dataTypes and validators in PropType, PropCheck
*     -- Must there are in all document: id, cName, creator, modifier
*
* data storage
* -----------
* system collections:
*   collections
*	     doc {id, cName, colName, creator, modifier}
*	     validator: <validator>,
*		   indexes:[propName, propName, ...]
*      accessRights:<AcessRights>
*	  docAccessRights
*		    doc {id, cName, creator, modified,
*			       docCname, docId, to, rights:['read', .....],
*			       refCname? refId}
*       indexes: ['id', 'docId']
*   users
*       doc {id, cName, creator, modifier, userName, pswHash}
*	  userGroups
*		    doc {id, cName, creator, modifier, userName, groupName}
*       indexes: ['id', 'userName']
* other documents
*    doc:{id, cName, creator, modifier, ....}
*    indexes: ['id', ...]
*/
// property types
var PropType;
(function (PropType) {
    PropType["number"] = "number";
    PropType["integer"] = "integer";
    PropType["chars"] = "chars";
    PropType["bool"] = "bool";
    PropType["date"] = "date";
    PropType["datetime"] = "datetime";
    PropType["pointer"] = "pointer";
    PropType["userName"] = "userName";
    PropType["values"] = "values";
    // You can define special dataTpes for your app ...
})(PropType || (PropType = {}));
/************************
* Document cursor class *
*************************/
var Dcursor = /** @class */ (function () {
    function Dcursor() {
        this.hashes = []; // array of Hashes
        this.pos = -1;
    }
    /**
    * get next -- check user accessRight control
    * @param string userName
    * @return DocumentHandler
    */
    Dcursor.prototype.next = function (userName) {
        var result = new DocumentHandler();
        this.pos++;
        if (this.pos >= this.hashes.length) {
            result.err = 'EOF';
        }
        else {
            result.readByHash(this.hashes[this.pos], userName);
            while ((result.err != '') && (this.pos < (this.hashes.length - 1))) {
                this.pos++;
                result.readByHash(this.hashes[this.pos], userName);
            }
        }
        if (this.pos >= this.hashes.length) {
            result.err = 'EOF';
        }
        if (result.err == 'GET_ERROR(3)') {
            result.err = 'EOF';
        }
        return result;
    };
    return Dcursor;
}());
/**************************
* Document handler class  *
***************************/
var DocumentHandler = /** @class */ (function () {
    function DocumentHandler() {
        this.doc = { "id": "", "cName": "", "creator": "", "modifier": "" };
        this.key = ''; // document or collection hash code
        this.err = ''; // error msg
        this.doc.id = '';
        this.doc.cName = '';
        this.doc.colName = '';
        this.doc.creator = '';
        this.doc.modifier = '';
    }
    /**
    * init document
    * @param object doc
    * @return void  -- set this.err
    */
    DocumentHandler.prototype.init = function (doc, userName) {
        this.doc = doc;
        this.key = '';
        this.err = '';
        this.collection = new DocumentHandler(); // collection object
        if (this.doc.cName != '') {
            this.collection.readByName(this.doc.cName, userName);
        }
        if ((this.doc.cName != 'collections') &&
            (this.checkAccessRight('read', userName) == false)) {
            this.err = 'ACCESS_DENIED';
        }
        return;
    };
    /**
    * read from database by hash -- user accessRight control
    * @param string hash
    * @param string userName
    * @return void  -- set this.err
    */
    DocumentHandler.prototype.readByHash = function (hash, userName) {
        var w;
        try {
            var s = get(hash);
            if (s instanceof Error) {
                this.err = 'GET_ERROR(1)';
                return;
            }
            this.err = '';
        }
        catch (err) {
            this.err = 'GET_ERROR(3)';
            return;
        }
        /*
        if (s.Hash != undefined) {
            this.err = 'GET_ERROR(2)';
            return;
        }
        */
        w = JSON.parse(s);
        this.doc = w.doc;
        if (this.doc.id == '') {
            this.doc.id = hash;
        }
        if (w.indexes != undefined) {
            this.indexes = w.indexes;
        }
        if (w.validator != undefined) {
            this.validator = w.validator;
        }
        if (w.accessRights != undefined) {
            this.accessRights = w.accessRights;
        }
        this.collection = new DocumentHandler();
        if (this.doc.cName != '') {
            this.collection.readByName(this.doc.cName, userName);
        }
        this.key = hash;
        if ((this.doc.cName != 'collections') &&
            (this.checkAccessRight('read', userName) == false)) {
            this.err = 'ACCESS_DENIED';
            this.doc = { "id": "", "cName": "", "colName": "", "creator": "", "modifier": "" };
        }
        return;
    };
    /**
    * read from database by hash -- check user accessRight
    * @param string cName
    * @param string id
    * @param string userName
    * @return void  and set this.err
    */
    DocumentHandler.prototype.readById = function (cName, id, userName) {
        var dc = this.doFind(cName, 'id', id, userName);
        var dh = dc.next(userName);
        if (dh.err != '') {
            this.err = dh.err;
            return;
        }
        this.doc = dh.doc;
        if (this.doc.id == '') {
            this.doc.id = dh.key;
        }
        this.collection = new DocumentHandler();
        if (this.doc.cName != '') {
            this.collection.readByName(this.doc.cName, userName);
        }
        if (dh.accessRights != undefined) {
            this.accessRights = dh.accessRights;
        }
        if (dh.indexes != undefined) {
            this.indexes = dh.indexes;
        }
        if (dh.validator != undefined) {
            this.validator = dh.validator;
        }
        if (this.checkAccessRight('read', userName) == false) {
            this.err = 'ACCESS_DENIED';
            this.doc = { "id": "", "cName": "", "colName": "", "creator": "", "modifier": "" };
        }
        return;
    };
    /**
    * read collection from database by colName -- check user accessRight
    * @param string colName
    * @param string userName
    * @return void  and set this.err
    */
    DocumentHandler.prototype.readByName = function (colName, userName) {
        this.err = '';
        if (colName == 'collections') {
            this.accessRights = { "reference": { "cName": "", "id": "" },
                "read": ['Gguest'],
                "insert": ['Gadmin'],
                "update": ['Gadmin'],
                "del": ['Gadmin']
            };
            this.indexes = ['id', 'colName'];
            this.validator = { "request": ['id', 'colName', 'cName', 'creator', 'modifier'],
                "propDefs": [{ "pName": "id",
                        "pType": PropType.chars,
                        "unique": true
                    },
                    { "pName": "colName",
                        "pType": PropType.chars,
                        "unique": true
                    }
                ]
            };
            this.collection = new DocumentHandler();
        }
        else {
            var dc = this.doFind('collections', 'colName', colName, userName);
            var dh = dc.next(userName);
            if (dh.err != '') {
                this.err = dh.err;
                return;
            }
            this.doc = dh.doc;
            if (this.doc.id == '') {
                this.doc.id = dh.key;
            }
            this.key = dh.key;
            if (dh.accessRights != undefined) {
                this.accessRights = dh.accessRights;
            }
            if (dh.accessRights != undefined) {
                this.accessRights = dh.accessRights;
            }
            if (dh.validator != undefined) {
                this.validator = dh.validator;
            }
            if (dh.indexes != undefined) {
                this.indexes = dh.indexes;
            }
            this.collection = new DocumentHandler();
            if (this.doc.cName != '') {
                this.collection.readByName(this.doc.cName, userName);
            }
            if (this.checkAccessRight('read', userName) == false) {
                this.err = 'ACCESS_DENIED';
                this.doc = { "id": "", "cName": "", "colName": "", "creator": "", "modifier": "" };
            }
            return;
        }
        return;
    };
    /**
    * get AccessRight from DHT docAccessRights
    * vparam string userName
    * @return AccessRights | undefined
    */
    DocumentHandler.prototype.getDocAccessRights = function (userName) {
        var result = undefined;
        var w = new DocumentHandler();
        var dc = w.doFind('docAccessRights', 'docId', this.doc.cName, userName);
        var dh = dc.next(userName);
        while (dh.err == 'OK') {
            if (dh.doc['docCname'] == this.doc.cName) {
                result = { reference: { cName: "", id: "" }, read: [], insert: [], update: [], del: [] };
                if (dh.doc['refCname'] != undefined) {
                    result.reference = { "cName": dh.doc['refCname'], "id": dh.doc['refId'] };
                }
                else {
                    if (dh.doc['rights'].indexOf('read') >= 0) {
                        result.read.push(dh.doc['to']);
                    }
                    if (dh.doc['rights'].indexOf('insert') >= 0) {
                        result.insert.push(dh.doc['to']);
                    }
                    if (dh.doc['rights'].indexOf('update') >= 0) {
                        result.update.push(dh.doc['to']);
                    }
                    if (dh.doc['rights'].indexOf('del') >= 0) {
                        result.del.push(dh.doc['to']);
                    }
                }
            }
            dh = dc.next(userName);
        }
        return result;
    };
    /**
    * chech userName accessRight for action
    * @param string 'insert'|'update'|'delete'|'read'
    * @return bool
    */
    DocumentHandler.prototype.checkAccessRight = function (action, userName) {
        if (userName == 'root') {
            return true;
        }
        var accessList = [];
        var result = false;
        var w;
        var docAccessRights = this.getDocAccessRights(userName);
        if (docAccessRights == undefined) {
            if (this.collection.accessRights != undefined) {
                if (this.collection.accessRights[action] != undefined) {
                    accessList = this.collection.accessRights[action];
                }
            }
        }
        else if (docAccessRights.reference.cName != '') {
            w = new DocumentHandler();
            w.readById(docAccessRights.reference.cName, docAccessRights.reference.id, userName);
            accessList = w.accessRights[action];
        }
        else if (docAccessRights[action] != undefined) {
            accessList = docAccessRights[action];
        }
        else if (this.collection.accessRights != undefined) {
            if (this.collection.accessRights[action] != undefined) {
                accessList = this.collection.accessRights[action];
            }
        }
        result = _accessRight(this.doc, userName, accessList);
        return result;
    };
    /**
    * insert this document to DHT -- no check user accessRight
    * @param string UuserName
    * @return void and set this.err
    */
    DocumentHandler.prototype._insert = function () {
        debug('\n_insert 1');
        var entry = { "doc": { "id": "", "cName": "", "creator": "", "modifier": "" } };
        var links = [];
        var tag;
        var i = 0;
        var key;
        var linkBase = App.DNA.Hash;
        entry.doc = this.doc;
        if (this.doc.cName == 'collections') {
            if (this.validator != undefined) {
                entry.validator = this.validator;
            }
            if (this.indexes != undefined) {
                entry.indexes = this.indexes;
            }
            if (this.accessRights != undefined) {
                entry.accessRights = this.accessRights;
            }
        }
        else {
            debug('\n_insert 2');
            this.collection.readByName(this.doc.cName, 'root');
            if (this.collection.err != '') {
                this.err = 'COLLECTION_NOT_EXISTS';
                return;
            }
        }
        debug('\n_insert 3');
        if (this.check() == false) {
            // this.err = 'CHECK_ERROR';
            return;
        }
        debug('\n_insert 5');
        key = commit('publicStr', JSON.stringify(entry));
        debug('\n_insert 6');
        if (key instanceof Error) {
            this.err = 'COMMIT_ERROR';
            debug('\n_insert 6.1');
            return;
        }
        if (this.doc.id == '') {
            this.doc.id = key;
        }
        debug('\n_insert 7');
        if (this.doc.cName != '') {
            this.collection.readByName(this.doc.cName, 'root');
            if (this.collection.indexes != undefined) {
                debug('\n_insert 8');
                if (this.collection.indexes.indexOf('id') < 0) {
                    this.collection.indexes.push('id');
                }
                links = [];
                tag = this.doc.cName + '_all';
                links.push({ Base: linkBase, Link: key, Tag: tag });
                for (i = 0; i < this.collection.indexes.length; i++) {
                    tag = this.doc.cName + '_' +
                        this.collection.indexes[i] + '_' +
                        this.doc[this.collection.indexes[i]];
                    links.push({ Base: linkBase, Link: key, Tag: tag });
                }
                debug('\n_insert 9');
                key = commit('publicLink', { "Links": links });
                if (key instanceof Error) {
                    this.err = 'COMMIT_ERROR(2)';
                    return;
                }
            }
        }
        debug('\n_insert end');
        return;
    };
    /**
    * insert this document to DHT -- check user accessRight
    * @param string UuserName
    * @return void and set this.err
    */
    DocumentHandler.prototype.insert = function (userName) {
        if (this.checkAccessRight('insert', userName) == false) {
            this.err = 'ACCESS_DENIED';
            return;
        }
        this.doc.creator = userName;
        this._insert();
        return;
    };
    /**
    * delete this document from DHT -- check User AccessRight
    * @param bool
    * @param string userName
    * @return void and set this.err
    */
    DocumentHandler.prototype.del = function (forUpdate, userName) {
        var oldDocumentHandler;
        var oldKey = '';
        var tag;
        var key = '';
        var links;
        var i = 0;
        var linkBase = App.DNA.Hash;
        if (forUpdate == undefined) {
            forUpdate = true;
        }
        this.doc.modifier = userName;
        if (forUpdate) {
            if (this.checkAccessRight('update', userName) == false) {
                this.err = 'ACCESS_DENIED';
                return;
            }
        }
        else {
            if (this.checkAccessRight('delete', userName) == false) {
                this.err = 'ACCESS_DENIED';
                return;
            }
            if (this.doc.cName == 'collections') {
                this.doEmpty(userName);
            }
        }
        // read old DocumentEntry
        var dc = this.doFind(this.doc.cName, 'id', this.doc.id, 'root');
        oldDocumentHandler = dc.next('root');
        if (oldDocumentHandler.err != '') {
            this.err = 'NOT_FOUND';
            return;
        }
        oldKey = oldDocumentHandler.key;
        // remove old Entry
        key = remove(oldKey, '');
        if (key instanceof Error) {
            this.err = 'REMOVE_ERROR(1)';
            return;
        }
        // remove old Links
        links = [];
        if (this.collection.indexes != undefined) {
            if (this.collection.indexes.indexOf('id') <= 0) {
                this.collection.indexes.push('id');
            }
            links = [];
            tag = this.doc.cName + '_all';
            links.push({ Base: linkBase, Link: oldKey, Tag: tag, Option: HC.LinkAction.Del });
            for (i = 0; i < this.collection.indexes.length; i++) {
                tag = this.doc.cName + '_' +
                    this.collection.indexes[i] + '_' +
                    oldDocumentHandler.doc[this.collection.indexes[i]];
                links.push({ Base: linkBase, Link: oldKey, Tag: tag, Option: HC.LinkAction.Del });
            }
            key = commit('publicLink', { "Links": links });
            if (key instanceof Error) {
                this.err = 'COMMIT_ERROR(2)';
                return;
            }
        }
        return;
    };
    /**
    * update this document in DHT  -- check user accessRight
    * @param string userName
    * @return void and set this.err
    */
    DocumentHandler.prototype.update = function (userName) {
        debug('\nupdate 1');
        var old;
        var i;
        if (this.doc.cName == 'collections') {
            old = new DocumentHandler();
            old.readByName(this.doc.colName, userName);
        }
        this.doc.modifier = userName;
        if (this.checkAccessRight('update', userName) == false) {
            this.err = 'ACCESS_DENIED';
            return;
        }
        debug('\nupdate 2');
        this.del(true, userName);
        debug('\nupdate 3');
        if (this.err == '') {
            this.insert(userName);
            debug('\nupdate 4');
        }
        else {
            return;
        }
        if (this.doc.cName == 'collections') {
            if (old.indexes == undefined) {
                old.indexes = [];
            }
            if (this.indexes == undefined) {
                this.indexes = [];
            }
            // drop old ignored indexes
            for (i = 0; i < old.indexes.length; i++) {
                if (this.indexes.indexOf(old.indexes[i]) < 0) {
                    this.dropIndex(old.indexes[i], old.doc);
                }
            }
            if (this.err == '') {
                // create new indexes
                for (i = 0; i < this.indexes.length; i++) {
                    if (old.indexes.indexOf(this.indexes[i]) < 0) {
                        this.createIndex(this.indexes[i]);
                    }
                }
            }
        }
        debug('\nupdate end');
        return;
    };
    /**
    * check this.doc by  collection.validator
    * @return boolean and set this.err
    */
    DocumentHandler.prototype.check = function () {
        var result = true;
        var i = 0;
        var w;
        var dh;
        var dc;
        var userName = '';
        if (this.doc.cName == 'collections') {
            // collections
            if (this.doc['colName'] == undefined) {
                this.err = 'COLNAME_REQUEST';
                result = false;
                return result;
            }
            if (this.doc.colName == '') {
                this.err = 'COLNAME_REQUEST';
                result = false;
                return result;
            }
            w = new DocumentHandler();
            dc = w.doFind('collections', 'colName', this.doc.colName, 'root');
            dh = dc.next('root');
            if ((dh.err == 'EOF') || (dh.doc.id == this.doc.id)) {
                result = true;
            }
            else {
                this.err = 'COLNAME_EXISTS';
                result = false;
            }
        }
        else {
            // document
            if (this.collection.validator != undefined) {
                if (this.collection.validator.request != undefined) {
                    for (i = 0; i < this.collection.validator.request.length; i++) {
                        if (this.doc[this.collection.validator.request[i]] == undefined) {
                            this.err = 'REQUEST ' + this.collection.validator.request[i];
                            result = false;
                        }
                    }
                }
                if (this.collection.validator.propDefs != undefined) {
                    for (i = 0; i < this.collection.validator.propDefs.length; i++) {
                        if (_propCheck(this.doc, this.collection.validator.propDefs[i]) == false) {
                            this.err = 'INVALID_VALUE ' + this.collection.validator.propDefs[i].pName;
                            result = false;
                        }
                    }
                }
            }
        }
        return result;
    };
    /**
    * delete all child document
    * @param string userName
    * @return void and set this.err
    */
    DocumentHandler.prototype.doEmpty = function (userName) {
        var dc = this.doFind(this.doc.colName, 'all', '', 'root');
        var dh = dc.next('root');
        while (dh.err == '') {
            dh.del(false, userName);
            if (dh.err != '') {
                this.err = dh.err;
            }
            dh = dc.next('root');
        }
        return;
    };
    /**
    * build Cursor for this child Documents by filer
    * @param string cName
    * @param string propName or 'all'
    * @param string propvale or '';
    * @param string userName
    * @return object Dcursor;
    */
    DocumentHandler.prototype.doFind = function (cName, propName, propValue, userName) {
        var result = new Dcursor();
        var tag;
        var i;
        var dh;
        var collection;
        var linkBase = App.DNA.Hash;
        dh = new DocumentHandler();
        if (propName == 'all') {
            tag = cName + '_all';
        }
        else {
            tag = cName + '_' + propName + '_' + propValue;
        }
        var w = getLinks(linkBase, tag, { "Load": false, StatusMask: HC.Status.Live });
        if (w instanceof Error) {
            this.err = 'GETLINKS_ERROR(1)';
            result.hashes = [];
        }
        else {
            result.hashes = [];
            for (i = 0; i < w.length; i++) {
                dh.readByHash(w[i].Hash, 'root');
                if (dh.err == '') {
                    result.hashes.push(w[i].Hash);
                }
            }
        }
        return result;
    };
    /**
    * create new index for all child documents
    * @param string property Name
    * @return void and set this.err
    */
    DocumentHandler.prototype.createIndex = function (propName) {
        var links = [];
        var tag = '';
        var dc = this.doFind(this.doc.colName, 'all', '', 'root');
        var dh = dc.next('root');
        var key = '';
        while (dh.err == '') {
            links = [];
            if (dh.doc[propName] != undefined) {
                tag = this.doc.cName + '_' +
                    propName + '_' +
                    dh.doc[propName];
                links.push({ Base: App.DNA.Hash, Link: dh.key, Tag: tag });
                key = commit('publicLink', { "Links": links });
            }
            dh = dc.next('root');
        }
        return;
    };
    /**
    * drop index from all child documents
    * @param string propName
    * @param object doc
    * @return void and set this.err
    */
    DocumentHandler.prototype.dropIndex = function (propName, oldDoc) {
        var links = [];
        var tag = '';
        var dc = this.doFind(this.doc.colName, 'all', '', 'root');
        var dh = dc.next('root');
        var key = '';
        while (dh.err == '') {
            links = [];
            if (dh.doc[propName] != undefined) {
                tag = this.doc.cName + '_' +
                    propName + '_' +
                    dh.doc[propName];
                links.push({ Base: App.DNA.Hash, Link: dh.key, Tag: tag, Option: HC.LinkAction.Del });
                key = commit('publicLink', { "Links": links });
            }
            dh = dc.next('root');
        }
        return;
    };
    return DocumentHandler;
}()); // DocumentHandler
/**
* check userName accessRight for accessList
* @param object document
* @param string userName
* @param array of string ['GgroupName', ..., 'UuserName', ..., 'creator', 'Gguest']
* @return bool
*/
function _accessRight(doc, userName, accessList) {
    if (userName == 'root') {
        return true;
    }
    var result = false;
    var userGroups = [];
    var i = 0;
    if (accessList.indexOf('Gguest') >= 0) {
        result = true;
    }
    else if ((accessList.indexOf('U' + userName) >= 0)) {
        result = true;
    }
    else if ((accessList.indexOf('creator') >= 0) &&
        (userName == doc.creator)) {
        result = true;
    }
    else {
        userGroups = _getUserGroups(userName);
        for (i = 0; i < userGroups.length; i++) {
            if (accessList.indexOf('G' + userGroups[i]) >= 0) {
                result = true;
            }
        }
    }
    return result;
}
/**
* get userGroups for userName
* @param string userName
* @return array of string
*/
function _getUserGroups(userName) {
    var result = ['guest'];
    var w = new DocumentHandler();
    var dc = w.doFind('userGroups', 'userName', userName, 'root');
    var dh = dc.next(userName);
    while (dh.err == '') {
        result.push(dh.doc['groupName']);
        dh = dc.next(userName);
    }
    return result;
}
/**
* check user registered?
* @param string userName.psw
* @return string userName | 'guest'
*/
function _checkUser(user) {
    var result = 'guest';
    var w = user.split('.');
    var dh = new DocumentHandler();
    if (w.length == 2) {
        var psw = w[1];
        var userName = w[0];
        var dc = dh.doFind('users', 'userName', userName, 'root');
        if (dc.hashes.length == 1) {
            dh = dc.next('root');
            if ((dh.err == '') && (SHA256(psw) == dh.doc['pswHash'])) {
                result = userName;
            }
        }
    }
    return result;
}
/**
* check doc by propDef
* @param object document
* @param object PropDef
* @return bool
*/
function _propCheck(doc, propDef) {
    var result = true;
    var pValue = doc[propDef.pName];
    if (pValue == undefined) {
        return true;
    }
    var d;
    var dc;
    var w;
    var dh;
    if (propDef.unique == true) {
        w = new DocumentHandler();
        dc = w.doFind(doc.cName, propDef.pName, doc[propDef.pName], 'root');
        dh = dc.next('root');
        if (dh.err == '') {
            return false;
        }
    }
    if (propDef.minValue != undefined) {
        if (pValue < propDef.minValue) {
            return false;
        }
    }
    if (propDef.maxValue != undefined) {
        if (pValue > propDef.maxValue) {
            return false;
        }
    }
    if (propDef.maxLength != undefined) {
        if (('' + pValue).length > propDef.maxLength) {
            return false;
        }
    }
    if (propDef.pType == PropType.integer) {
        if ((0 + pValue) != pValue) {
            result = false;
        }
        else if (pValue === parseInt(pValue, 10)) {
            result = true;
        }
        else {
            result = false;
        }
    }
    else if (propDef.pType == PropType.number) {
        if ((0 + pValue) != pValue) {
            result = false;
        }
    }
    else if (propDef.pType == 'bool') {
        if (!(typeof (pValue) == "boolean")) {
            result = false;
        }
    }
    else if (propDef.pType == PropType.date) {
        d = new Date(pValue);
        if (isNaN(d.getDay())) {
            result = false;
        }
        if (d.getTime() <= 0) {
            result = false;
        }
        if (d.getHours() > 0) {
            result = false;
        }
        if (d.getMinutes() > 0) {
            result = false;
        }
        if (d.getSeconds() > 0) {
            result = false;
        }
    }
    else if (propDef.pType == PropType.datetime) {
        d = new Date(pValue);
        if (isNaN(d.getDay())) {
            result = false;
        }
    }
    else if (propDef.pType == PropType.userName) {
        w = new DocumentHandler();
        dc = w.doFind('users', 'userName', pValue, 'root');
        result = (dc.hashes.length >= 1);
    }
    else if (propDef.pType == PropType.pointer) {
        w = new DocumentHandler();
        dc = w.doFind(propDef.reference, 'id', pValue, 'root');
        result = (dc.hashes.length >= 1);
    }
    else if (propDef.pType == PropType.values) {
        if (propDef.values.indexOf(pValue) < 0) {
            result = false;
        }
        /*
        } else if (propDef.pType == PropType.????) {
         can yuu write special validator here
        */
    }
    return result;
}
;
function SHA256(s) {
    var chrsz = 8;
    var hexcase = 0;
    function safe_add(x, y) {
        var lsw = (x & 0xFFFF) + (y & 0xFFFF);
        var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
        return (msw << 16) | (lsw & 0xFFFF);
    }
    function S(X, n) { return (X >>> n) | (X << (32 - n)); }
    function R(X, n) { return (X >>> n); }
    function Ch(x, y, z) { return ((x & y) ^ ((~x) & z)); }
    function Maj(x, y, z) { return ((x & y) ^ (x & z) ^ (y & z)); }
    function Sigma0256(x) { return (S(x, 2) ^ S(x, 13) ^ S(x, 22)); }
    function Sigma1256(x) { return (S(x, 6) ^ S(x, 11) ^ S(x, 25)); }
    function Gamma0256(x) { return (S(x, 7) ^ S(x, 18) ^ R(x, 3)); }
    function Gamma1256(x) { return (S(x, 17) ^ S(x, 19) ^ R(x, 10)); }
    function core_sha256(m, l) {
        var K = new Array(0x428A2F98, 0x71374491, 0xB5C0FBCF, 0xE9B5DBA5, 0x3956C25B, 0x59F111F1, 0x923F82A4, 0xAB1C5ED5, 0xD807AA98, 0x12835B01, 0x243185BE, 0x550C7DC3, 0x72BE5D74, 0x80DEB1FE, 0x9BDC06A7, 0xC19BF174, 0xE49B69C1, 0xEFBE4786, 0xFC19DC6, 0x240CA1CC, 0x2DE92C6F, 0x4A7484AA, 0x5CB0A9DC, 0x76F988DA, 0x983E5152, 0xA831C66D, 0xB00327C8, 0xBF597FC7, 0xC6E00BF3, 0xD5A79147, 0x6CA6351, 0x14292967, 0x27B70A85, 0x2E1B2138, 0x4D2C6DFC, 0x53380D13, 0x650A7354, 0x766A0ABB, 0x81C2C92E, 0x92722C85, 0xA2BFE8A1, 0xA81A664B, 0xC24B8B70, 0xC76C51A3, 0xD192E819, 0xD6990624, 0xF40E3585, 0x106AA070, 0x19A4C116, 0x1E376C08, 0x2748774C, 0x34B0BCB5, 0x391C0CB3, 0x4ED8AA4A, 0x5B9CCA4F, 0x682E6FF3, 0x748F82EE, 0x78A5636F, 0x84C87814, 0x8CC70208, 0x90BEFFFA, 0xA4506CEB, 0xBEF9A3F7, 0xC67178F2);
        var HASH = new Array(0x6A09E667, 0xBB67AE85, 0x3C6EF372, 0xA54FF53A, 0x510E527F, 0x9B05688C, 0x1F83D9AB, 0x5BE0CD19);
        var W = new Array(64);
        var i, j;
        var a, b, c, d, e, f, g, h;
        var T1, T2;
        m[l >> 5] |= 0x80 << (24 - l % 32);
        m[((l + 64 >> 9) << 4) + 15] = l;
        for (i = 0; i < m.length; i += 16) {
            a = HASH[0];
            b = HASH[1];
            c = HASH[2];
            d = HASH[3];
            e = HASH[4];
            f = HASH[5];
            g = HASH[6];
            h = HASH[7];
            for (var j = 0; j < 64; j++) {
                if (j < 16)
                    W[j] = m[j + i];
                else
                    W[j] = safe_add(safe_add(safe_add(Gamma1256(W[j - 2]), W[j - 7]), Gamma0256(W[j - 15])), W[j - 16]);
                T1 = safe_add(safe_add(safe_add(safe_add(h, Sigma1256(e)), Ch(e, f, g)), K[j]), W[j]);
                T2 = safe_add(Sigma0256(a), Maj(a, b, c));
                h = g;
                g = f;
                f = e;
                e = safe_add(d, T1);
                d = c;
                c = b;
                b = a;
                a = safe_add(T1, T2);
            }
            HASH[0] = safe_add(a, HASH[0]);
            HASH[1] = safe_add(b, HASH[1]);
            HASH[2] = safe_add(c, HASH[2]);
            HASH[3] = safe_add(d, HASH[3]);
            HASH[4] = safe_add(e, HASH[4]);
            HASH[5] = safe_add(f, HASH[5]);
            HASH[6] = safe_add(g, HASH[6]);
            HASH[7] = safe_add(h, HASH[7]);
        }
        return HASH;
    }
    function str2binb(str) {
        var bin = Array();
        var mask = (1 << chrsz) - 1;
        for (var i = 0; i < str.length * chrsz; i += chrsz) {
            bin[i >> 5] |= (str.charCodeAt(i / chrsz) & mask) << (24 - i % 32);
        }
        return bin;
    }
    function Utf8Encode(string) {
        string = string.replace(/\r\n/g, "\n");
        var utftext = "";
        for (var n = 0; n < string.length; n++) {
            var c = string.charCodeAt(n);
            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if ((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }
        }
        return utftext;
    }
    function binb2hex(binarray) {
        var hex_tab = hexcase ? "0123456789ABCDEF" : "0123456789abcdef";
        var str = "";
        for (var i = 0; i < binarray.length * 4; i++) {
            str += hex_tab.charAt((binarray[i >> 2] >> ((3 - i % 4) * 8 + 4)) & 0xF) +
                hex_tab.charAt((binarray[i >> 2] >> ((3 - i % 4) * 8)) & 0xF);
        }
        return str;
    }
    s = Utf8Encode(s);
    return binb2hex(core_sha256(str2binb(s), s.length * chrsz));
}
/*******************************************************************************
*  callback functions, requed holochain functions
*******************************************************************************/
function genesis() {
    // create  noSqlSetuo, userGroups, docAccessRights collections if not exists
    var w = new DocumentHandler();
    var dc = w.doFind('collections', 'noSqlSetup', 'all', 'root');
    if ((dc instanceof Error) || (dc.hashes.length <= 0)) {
        // create collections
        w.init({ "id": "", "cName": "collections", "creator": "root", "modifier": "",
            "colName": "users" }, 'root');
        w.indexes = ['id', 'userName'];
        w.validator = { "request": ["cName", "creator", "userName", "pswHash"],
            "propDefs": [
                { "pName": "userName", "pType": PropType.chars, "unique": true },
                { "pName": "pswHash", "pType": PropType.chars },
                { "pName": "num", "pType": PropType.integer }
            ]
        };
        w.accessRights = { "reference": { "cName": "", "id": "" },
            "read": ["Gguest"],
            "insert": ["Gguest"],
            "update": ["Gadmin", "Gccreator"],
            "del": ["Gadmin", "Gcreator"]
        };
        w._insert();
        w.init({ "id": "", "cName": "collections", "creator": "root", "modifier": "",
            "colName": "userGroups" }, 'root');
        w.indexes = ['id', 'userName', 'groupName'];
        w.validator = { "request": ["cName", "creator",
                "userName", "groupName"],
            "propDefs": [
                { "pName": "userName", "pType": PropType.chars },
                { "pName": "pswHash", "pType": PropType.chars }
            ]
        };
        w.accessRights = { "reference": { "cName": "", "id": "" },
            "read": ["Gguest"],
            "insert": ["Gadmin"],
            "update": ["Gadmin"],
            "del": ["Gadmin"]
        };
        w._insert();
        w.init({ "id": "", "cName": "collections", "creator": "root", "modifier": "",
            "colName": "docAccessRights" }, 'root');
        w.indexes = ['id', 'docId'];
        w.validator = { "request": ["cName", "creator",
                "docCname", "docId", "to", "rights"],
            "propDefs": [
                { "pName": "docCname", "pType": PropType.chars },
                { "pName": "docId", "pType": PropType.chars },
                { "pName": "to", "pType": PropType.chars }
            ]
        };
        w.accessRights = { "reference": { "cName": "", "id": "" },
            "read": ["Gguest"],
            "insert": ["Gadmin"],
            "update": ["Gadmin"],
            "del": ["Gadmin"]
        };
        w._insert();
        w.init({ "id": "", "cName": "collections", "creator": "root", "modifier": "",
            "colName": "testCol" }, 'root');
        w.indexes = ['id'];
        w.validator = { "request": [],
            "propDefs": [
                { "pName": "i", "pType": PropType.integer, "minValue": 10, "maxValue": 20 },
                { "pName": "n", "pType": PropType.number },
                { "pName": "c", "pType": PropType.chars, "maxLength": 6 },
                { "pName": "b", "pType": PropType.bool },
                { "pName": "d", "pType": PropType.date },
                { "pName": "t", "pType": PropType.datetime },
                { "pName": "p", "pType": PropType.pointer, "reference": "users" },
                { "pName": "u", "pType": PropType.userName },
                { "pName": "v", "pType": PropType.values, "values": ["1", "2", "3", "4"] }
            ]
        };
        w.accessRights = { "reference": { "cName": "", "id": "" },
            "read": ["Gguest"],
            "insert": ["Gadmin", "Gsimple"],
            "update": ["Gadmin", "Usimple"],
            "del": ["Gadmin"]
        };
        w._insert();
        // create "root" user
        w.init({ "id": "", "cName": "users", "creator": "root", "modifier": "",
            "userName": "root", "pswHash": SHA256('123456') }, 'root');
        w.indexes = undefined;
        w.accessRights = undefined;
        w.validator = undefined;
        w._insert();
        // add root user into "admin" group
        w.init({ "id": "", "cName": "userGroups", "creator": "root", "modifier": "",
            "userName": "root", "groupName": "admin" }, 'root');
        w.indexes = undefined;
        w.accessRights = undefined;
        w.validator = undefined;
        w._insert();
        // create simple user
        w.init({ "id": "", "cName": "users", "creator": "root", "modifier": "",
            "userName": "simple", "pswHash": SHA256('123456') }, 'root');
        w.indexes = undefined;
        w.accessRights = undefined;
        w.validator = undefined;
        w._insert();
        // add simple user into "simple" group
        w.init({ "id": "", "cName": "userGroups", "creator": "root", "modifier": "",
            "userName": "simple", "groupName": "simple" }, 'root');
        w.indexes = undefined;
        w.accessRights = undefined;
        w.validator = undefined;
        w._insert();
    }
    return true;
}
/**
 * bridge genesis  - only if use bridges
 *
function bridgeGenesis(side,dna,appData) {
  var result = true;
  ('bridgegenesis side='+side+' dna'+dna);
  // if kell ellenörizni, hogy a megfelelő apphívta-e?
  return result;
}
*/
function validateCommit(entryType, entry, header, pkg, sources) {
    var res = true;
    var dh;
    var entryObj;
    var i;
    var w;
    var link;
    var tagItems;
    if (entryType == 'publicStr') {
        // document or collection insert or update
        // chec doc valid and check user access right
        entryObj = JSON.parse(entry);
        dh = new DocumentHandler();
        dh.doc = entryObj.doc;
        dh.collection = new DocumentHandler();
        dh.collection.readByName(entryObj.doc.cName, 'root');
        res = dh.check();
        if (res) {
            if (dh.doc.id == '') {
                res = dh.checkAccessRight('insert', dh.doc.creator);
            }
            else {
                res = dh.checkAccessRight('update', dh.doc.modifier);
            }
        }
    }
    else {
        // links insert or delete
        // chec link + Tag valid and check user access right
        dh = new DocumentHandler();
        for (i = 0; i < entry.Links.length; i++) {
            link = entry.Links[i];
            if (link.Option != 'd') {
                dh.readByHash(link.Link, 'root');
                res = (dh.err == '');
                if (res) {
                    tagItems = link.Tag.split('_');
                    res = ((tagItems[0] == dh.doc.cName) &&
                        ((tagItems[2] == dh.doc[tagItems[1]]) || (tagItems[1] == 'all')));
                }
                if (dh.doc.id == link.Link) {
                    res = dh.checkAccessRight('insert', dh.doc.creator);
                }
                else {
                    res = dh.checkAccessRight('update', dh.doc.modifier);
                }
            } // Option != 'd'
        } // for
    }
    return res;
}
function validatePut(entry_type, entry, header, pkg, sources) {
    var res = validateCommit(entry_type, entry, header, pkg, sources);
    return res;
}
function validateLink(entryType, hash, links, pkg, sources) {
    //debug('\nvalidateLink '+hash+' '+entryType);
    var res = true;
    return res;
}
function validateMod(entry_type, entry, header, replaces, pkg, sources) {
    debug('\nvalidateMod ' + entry_type + ' ' + entry);
    var res = true;
    return res;
}
function validateDel(entry_type, hash, pkg, sources) {
    debug('\nvalidateDel ' + entry_type + ' ' + hash);
    var res = true;
    return res;
}
function validatePutPkg(entry_type) {
    // debug('\nvalidatePutPkg');
    var res = true;
    return res;
}
function validateModPkg(entry_type) {
    // debug('\nvalidateModkg');
    var res = true;
    return res;
}
function validateDelPkg(entry_type) {
    // debug('\nvalidateDelPkg');
    var res = true;
    return res;
}
function validateLinkPkg(entry_type) {
    // debug('\nvalidateLinkPkg');
    var res = true;
    return res;
}
/*******************************************************************************
 * Public functions
 ******************************************************************************/
/**
* add new document into collectionc or create new dcollection
* @param jsonString
* 	doc object
*		accessRights?
*		validator?
*		indexes?
*  	user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*   doc object
*		info?
*/
function addDocument(jsonParam) {
    var result = { "state": "OTHER_ERROR", "doc": {}, "info": "" };
    var param = JSON.parse(jsonParam);
    var w = new DocumentHandler();
    var userName = _checkUser(param.user);
    w.init(param['doc'], userName);
    if (param['accessRights'] != undefined) {
        w.accessRights = param['accessRights'];
    }
    if (w.doc.cName == 'collections') {
        if (param['validator'] != undefined) {
            w.validator = param['validator'];
        }
        if (param['indexes'] != undefined) {
            w.indexes = param['indexes'];
        }
    }
    w.doc['creator'] = userName;
    w.insert(userName);
    if (w.err == '') {
        result.state = 'OK';
        result.doc = w.doc;
    }
    else {
        result.state = w.err;
    }
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
/**
* delete document or drop collection
* @param jsonString
*   doc object
*   user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*   doc object
*		info?
*/
function delDocument(jsonParam) {
    var result = { "state": "OTHER_ERROR", "info": "" };
    var param = JSON.parse(jsonParam);
    var w = new DocumentHandler();
    var userName = _checkUser(param.user);
    w.init(param['doc'], userName);
    w.doc['modifier'] = userName;
    w.del(false, userName);
    if (w.err == '') {
        result.state = 'OK';
    }
    else {
        result.state = w.err;
    }
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
/**
* update document
* @param jsonString
*		doc object
*	 	accessRights?
*	 	validator?
*	 	indexes?
*   user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*   doc object
*		info?
*/
function updateDocument(jsonParam) {
    var result = { "state": "OTHER_ERROR", "doc": {}, "info": "" };
    var param = JSON.parse(jsonParam);
    var w = new DocumentHandler();
    var userName = _checkUser(param.user);
    w.init(param['doc'], userName);
    w.accessRights = param['accessRights'];
    w.doc['modifier'] = userName;
    if (w.doc['cName'] == 'collections') {
        w.validator = param['validator'];
        w.indexes = param['indexes'];
    }
    w.update(userName);
    if (w.err == '') {
        result.state = 'OK';
        result.doc = w.doc;
    }
    else {
        result.state = w.err;
    }
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
/**
* do find cursor
* @param jsonString
*   cName
*   propName or 'all'
*	 	propValue
*   user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*   hashes
*   pos
*		info?
*/
function doFind(jsonParam) {
    var result = { "state": "OTHER_ERROR", "hashes": [], "pos": -1, "info": "" };
    var param = JSON.parse(jsonParam);
    var w = new DocumentHandler();
    var dc;
    var userName = _checkUser(param.user);
    var dc = w.doFind(param['cName'], param['propName'], param['propValue'], userName);
    result.state = 'OK';
    result.hashes = dc.hashes;
    result.pos = dc.pos;
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
/**
* get next document by cursor
* @param jsonString
*   hashes
*   pos
*   user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*   doc
*   accessRights
*   validator
*   indexes
*   hashes
*   pos
*		info?
*/
function doNext(jsonParam) {
    var result = { "state": "OTHER_ERROR", "doc": {},
        "accessRights": {}, "validator": {}, "indexes": [], "hashes": [], "pos": -1, "info": "" };
    var param = JSON.parse(jsonParam);
    var hd;
    var w = new Dcursor();
    var userName = _checkUser(param.user);
    w.hashes = param.hashes;
    w.pos = param.pos;
    hd = w.next(userName);
    if (hd.err == '') {
        result.doc = hd.doc;
        result.accessRights = hd.accessRights;
        if (hd.doc.cName == 'collections') {
            result.validator = hd.validator;
            result.indexes = hd.indexes;
        }
        result.pos = w.pos;
        result.hashes = w.hashes;
        result.state = 'OK';
    }
    else {
        result.state = hd.err;
    }
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
/**
* do find cursor and get frist document
* @param jsonString
*   cName
*   propName or 'all'
*	 	propValue
*   user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*   doc
*   accessRights
*   validator
*   indexes
*   hashes
*   pos
*		info?
*/
function first(jsonParam) {
    var result = { "state": "OTHER_ERROR", "doc": {},
        "accessRights": {}, "validator": {}, "indexes": [], "hashes": [], "pos": -1, "info": "" };
    var param = JSON.parse(jsonParam);
    var dh;
    var w;
    var dc;
    var userName = _checkUser(param.user);
    w = new DocumentHandler();
    dc = w.doFind(param.cName, param.propName, param.propValue, userName);
    dh = dc.next(userName);
    if (dh.err == '') {
        result.doc = dh.doc;
        if (dh.doc.cName == 'collections') {
            result.accessRights = dh.accessRights;
            result.validator = dh.validator;
            result.indexes = dh.indexes;
        }
        result.pos = dc.pos;
        result.hashes = dc.hashes;
        result.state = 'OK';
    }
    else {
        result.state = dh.err;
    }
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
/**
* delete all childs document
* @param jsonString
*   colName
*   user string userName.psw
*		info?
* @return jsonString
*   state string "OK"|errorStr
*		info?
*/
function doEmpty(jsonParam) {
    var result = { "state": "OTHER_ERROR", "info": "" };
    var param = JSON.parse(jsonParam);
    var w = new DocumentHandler();
    var userName = _checkUser(param.user);
    w.readByName(param['colName'], userName);
    w.doEmpty(userName);
    if (w.err == '') {
        result.state = 'OK';
    }
    else {
        result.state = w.err;
    }
    if (param['info'] != undefined) {
        result.info = param['info'];
    }
    return JSON.stringify(result);
}
