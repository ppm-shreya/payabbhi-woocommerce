VERSION_FILE=VERSION
VER=`cat $(VERSION_FILE)`

release: install init prepare archive cleanup

install:
	composer require payabbhi/payabbhi-php

init:
	mkdir dist

prepare:
	mkdir payabbhi
	cp -R images payabbhi/
	cp payabbhi-payments.php payabbhi/
	mv vendor payabbhi/
	cp README.md payabbhi/
	cp VERSION  payabbhi/

archive:
	cd payabbhi && zip -r ../payabbhi-woocommerce-$(VER).zip * && cd ..
	cd payabbhi && tar -cvzf ../payabbhi-woocommerce-$(VER).tar.gz * && cd ..

cleanup:
	mv payabbhi-woocommerce-$(VER).zip dist
	mv payabbhi-woocommerce-$(VER).tar.gz dist
	rm composer.*
	rm -rf payabbhi

clean:
	rm -rf dist
