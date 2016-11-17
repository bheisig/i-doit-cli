TITLE = $(shell make -s get-setting-title)
VERSION = $(shell make -s get-setting-version)
TAG = $(shell make -s get-setting-tag)
DISTFILES = bin/ src/ vendor/ COPYING project.json README
DISTDIR = $(TAG)
DISTTARBALL = $(TAG)-$(VERSION).tar.gz

all : build

build :
	./vendor/bin/phar-composer build .
	mv idoitcli.phar idoit

install :
	test -x idoit
	install -m 775 idoit /usr/local/bin/

get-setting-% :
	php -r '$$project = json_decode(trim(file_get_contents("project.json")), true); echo $$project["$*"];'

readme :
	pandoc --from markdown --to plain --smart README.md > README

dist : readme
	rm -rf $(DISTDIR)/
	mkdir $(DISTDIR)/
	cp -r $(DISTFILES) $(DISTDIR)/
	tar czf $(DISTTARBALL) $(DISTDIR)/
	rm -r $(DISTDIR)/

tag :
	git tag -s -m "Tagging version $(VERSION)" $(VERSION)


## Clean up

clean :
	rm -f *.tar.gz README


## Development

gource :
	gource -1280x720 --seconds-per-day 3 --auto-skip-seconds 1 --title "$(TITLE)"

gitstats :
	gitstats -c project_name="$(TITLE)" . gitstats

phpdox :
	phpdox

phploc :
	phploc --exclude=vendor --exclude=tests .
