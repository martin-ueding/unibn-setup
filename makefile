# Copyright (c) 2011 Martin Ueding <dev@martin-ueding.de>

unibn_setup: unibn_setup.php 99bonnet vpnc-restarter CHANGELOG
	php $< > $@.build
	mv $@.build $@
	chmod +x $@

CHANGELOG: .git/HEAD
	git changelog | sed 's/.*/# &/' > $@.tmp
	mv $@.tmp > $@

.PHONY: clean
clean:
	$(RM) unibn_setup
