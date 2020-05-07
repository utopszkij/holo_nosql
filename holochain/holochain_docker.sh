#!/bin/bash
cd /home/utopszkij
docker run -i -t --name=holoc -p 8888:8888 -v /home/utopszkij:/home/utopszkij holonix1 \
nix-shell https://holochain.love

