# Copyright (c) 2011 Martin Ueding <dev@martin-ueding.de>

unibn_setup: unibn_setup.php 99bonnet vpnc-restarter CHANGELOG
	php $< > $@.build
	mv $@.build $@
	chmod +x $@
	chmod -w $@

CHANGELOG: .git/HEAD
	git changelog | sed 's/.*/# &/' > $@.build
	mv $@.build > $@

l10n: unibn_setup.pot

unibn_setup.pot: unibn_setup 99bonnet vpnc-restarter
	bash --dump-po-strings unibn_setup > $@.tmp
	bash --dump-po-strings 99bonnet >> $@.tmp
	bash --dump-po-strings vpnc-restarter >> $@.tmp
	mv $@.tmp $@


.PHONY: clean
clean:
	$(RM) unibn_setup
	$(RM) *.build
