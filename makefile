# Copyright (c) 2011 Martin Ueding <dev@martin-ueding.de>

unibn_setup: unibn_setup.php 99bonnet.asc vpnc-restarter.asc CHANGELOG de.mo.asc
	php $< > $@.build
	mv $@.build $@
	chmod +x $@

CHANGELOG: .git/HEAD
	git changelog | sed 's/.*/# &/' > $@.build
	mv $@.build $@

l10n: unibn_setup.pot

unibn_setup.pot: unibn_setup 99bonnet vpnc-restarter
	$(RM) $@.build
	bash --dump-po-strings unibn_setup >> $@.build
	bash --dump-po-strings 99bonnet >> $@.build
	bash --dump-po-strings vpnc-restarter >> $@.build
	mv $@.build $@

de.mo: de.po
	msgfmt -o $@ $^

%.asc: %
	base64 $^ > $@

.PHONY: clean
clean:
	$(RM) unibn_setup
	$(RM) *.build
	$(RM) de.mo de.mo.asc
	$(RM) unibn_setup.pot
