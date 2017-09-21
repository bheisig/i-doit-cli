TITLE = $(shell make -s get-setting-title)
VERSION = $(shell make -s get-setting-version)
TAG = $(shell make -s get-setting-tag)
DISTFILES = idoit LICENSE README
DISTDIR = $(TAG)
DISTTARBALL = $(TAG)-$(VERSION).tar.gz
BINDIR = $(DESTDIR)/usr/local/bin

all : build

build :
	php -d phar.readonly=off ./vendor/bin/phar-composer build https://github.com/bheisig/i-doit-cli.git
	mv idoitcli.phar idoit

install :
	test -x idoit
	install -m 775 idoit $(BINDIR)/idoit
	install -m 644 idoit.bash-completion $(DESTDIR)/etc/bash_completion.d/idoit

make uninstall :
	rm -f $(BINDIR)/idoit
	rm -f $(DESTDIR)/etc/bash_completion.d/idoit

get-setting-% :
	php -r '$$project = json_decode(trim(file_get_contents("project.json")), true); echo $$project["$*"];'

readme :
	pandoc --from markdown --to plain --smart README.md > README

dist : readme
	test -x idoit
	rm -rf $(DISTDIR)/
	mkdir $(DISTDIR)/
	cp -r $(DISTFILES) $(DISTDIR)/
	tar czf $(DISTTARBALL) $(DISTDIR)/
	rm -r $(DISTDIR)/

tag :
	git tag -s -m "Release version $(VERSION)" $(VERSION)

phive :
	test -x idoit
	cp idoit idoit.phar
	gpg --detach-sign --output idoit.phar.asc idoit.phar


## Clean up

clean :
	rm -f *.tar.gz README idoit idoit.phar idoit.phar.asc


## Development

gource :
	gource -1280x720 --seconds-per-day 3 --auto-skip-seconds 1 --title "$(TITLE)"

gitstats :
	gitstats -c project_name="$(TITLE)" . gitstats

phpdox :
	phpdox

phploc :
	phploc --exclude=vendor --exclude=tests .
