# Copyright (c) 2011 Martin Ueding <dev@martin-ueding.de>

unibn_setup: unibn_setup.php 99bonnet.asc CHANGELOG de.mo.asc
	php $< > $@.build
	mv $@.build $@
	chmod +x $@

CHANGELOG: .git/HEAD
	git changelog | sed 's/.*/# &/' > $@.build
	mv $@.build $@

l10n: unibn_setup.pot

unibn_setup.pot: unibn_setup 99bonnet
	$(RM) $@.build
	bash --dump-po-strings unibn_setup >> $@.build
	bash --dump-po-strings 99bonnet >> $@.build
	mv $@.build $@

de.mo: de.po
	msgfmt -o $@ $^

%.asc: %
	base64 $^ > $@

.PHONY: clean
clean:
	$(RM) unibn_setup
	$(RM) *.build
	$(RM) de.mo
	$(RM) unibn_setup.pot
	$(RM) *.asc
