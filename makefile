# Copyright (c) 2011 Martin Ueding <dev@martin-ueding.de>

unibn_setup: unibn_setup.php 99bonnet vpnc-restarter
	php $< > $@
	chmod +x $@

.PHONY: clean
clean:
	$(RM) unibn_setup
