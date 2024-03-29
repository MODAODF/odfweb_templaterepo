# Makefile for building the project

app_name=templaterepo
project_dir=$(CURDIR)/../$(app_name)
build_dir=$(project_dir)/build
appstore_dir=$(build_dir)/appstore
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
webpack=node_modules/.bin/webpack
version+=3.1.1
BRANCH:=$(shell cat dist_git_branch 2> /dev/null || git rev-parse --abbrev-ref HEAD 2> /dev/null)

jssources=$(wildcard js/*) $(wildcard js/*/*) $(wildcard css/*/*)  $(wildcard css/*)
othersources=$(wildcard appinfo/*) $(wildcard css/*/*) $(wildcard controller/*/*) $(wildcard templates/*/*) $(wildcard log/*/*)

all: build/main.js

test:
	@touch $(BRANCH).txt
clean:
	rm -rf $(sign_dir)
	rm -rf $(build_dir)/$(app_name)-$(version)-$(BRANCH).tar.gz
	rm -rf node_modules

node_modules: package.json
	npm install --deps

build/main.js: node_modules $(jssources)
	npm run build

.PHONY: watch
watch: node_modules
	$(webpack) serve --hot --port 3000 --public localcloud.icewind.me:444 --config webpack.dev.config.js

release: appstore create-tag

create-tag:
	git tag -a $(BRANCH)-$(version) -m "Tagging the $(BRANCH)-$(version) release."
	git push origin $(BRANCH)-$(version)

appstore: clean build/main.js
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/.babelrc.js \
	--exclude=/.drone.yml \
	--exclude=/.git \
	--exclude=/.gitattributes \
	--exclude=/.github \
	--exclude=/.gitignore \
	--exclude=/.php_cs.dist \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/.tx \
	--exclude=/build \
	--exclude=/CONTRIBUTING.md \
	--exclude=/Makefile \
	--exclude=/README.md \
	--exclude=/build/sign \
	--exclude=/composer.json \
	--exclude=/composer.lock \
	--exclude=/docs \
	--exclude=/issue_template.md \
	--exclude=/l10n/l10n.pl \
	--exclude=/node_modules \
	--exclude=/package-lock.json \
	--exclude=/package.json \
	--exclude=/postcss.config.js \
	--exclude=/src \
	--exclude=/tests \
	--exclude=/translationfiles \
	--exclude=/tsconfig.json \
	--exclude=/vendor \
	--exclude=/webpack.* \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version)-$(BRANCH).tar.gz \
		-C $(sign_dir) $(app_name)
