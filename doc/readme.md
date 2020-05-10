# Holochain fejlesztés docker környezetben

## holchain-rust docker image letöltése és frissitése

docker pull holochain/holonix 

docker run -i -t --name=holoc -p 3141 -v **path**:**path** holochain/holonix

nix-shell https://holochain.love

nix-shell# exit (nix-shell -ből vissszamegy a docker shell -be

exit   (kilép a docker container shellből visssza a gazdagép shell-be)
de a dockerben a rendszer fut tovább.

## docker image futatása egy lokális könyvtár becsatolásával

docker run -i -t --name=holoc -p 3141 -v **path**:**path** holonix

nix-shell https://holochain.love
	átmegy a dockerben "nix-shell" -be
	ezt az állapotot a továbbiakban **nix-shell#** -el jelzem

kilépés a nix-shell .ből, vissza a docker container konzolba:

nix-shell# exit

kilépés a docker skell konzolból (de az fiut tovább):

exit


## további docker müveletek (gazdagép konzolban)

Ha ujra  akarunk csatlakozni terminállal:

docker attach -i -z holoc

Ha le akarjuk állítani a docker containert

docker stop holoc

Ha újra akarujuk inditani

docker run holoc

Ha tötrölni akarjuk a docker containert:

docker rm holoc

docker container pillanatnyi állapotának mentése docker image -be:

docker commit holoc imagename

## Új holochain app létrehozása

cd **holochainAppsDir**
hc init **appName**

## új zome létrehozása az app -ba
cd **holochainAppsDir**/**appName**

hc generate zomes/**zomeName** rust-proc

- ezután kell a **zomeName**/code/src/lib.rs fájlban a RUST kodok megirni. A publik funkciók https post -al is hivhatóklesznek

## rust kód forditása

nix-shell# cd appRootPath
nix_shell# hc package

	elöször soká tart, továbbiakban gyorsabb

## unit test

		nekem ezt nem sikerült dockerben használnom

Inditani kell a sim2h_server teszt holchain node -ot:

másik terminálban
		nix_shell https://holochain.love

		nix-shell# sim2h_server  ( kilépés ctrl/c -vel)

		(dockerben talán: docker exec -i -t  containerName bash
		indit másik terminált)

nix-shell# cd appRootPath
nix-shell# hc test


## holochain app inditása lokális teszt üzemmód

nix-shell# cd appRootPath

nix-shell# hc run -i http

automatikus RUST forditással együtt:

nix-shell# hc run -i http -b

## holochain app éles inditása (feltehetően)

nix-shell# holochain run -i http ...........


## egyebek

 	A conductor-config.toml file konfigurálja a http portot, instance_id -t.

curl -X POST -H "Content-Type: application/json" -d '{"id": "0", "jsonrpc": "2.0", "method": "call", "params": {"instance_id": "test-instance", "zome": "hello", "function": "hello_holo", "args": {} }}' http://127.0.0.1:8888

hívással aktivizálhatóak a publikus funkciók.


Megjegyzés a hc run inditás után a konzolon debug infok vannak, amik olvashatóan tartalmazzák az adatokat :(

