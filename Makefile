BILLS=$(wildcard *.pdf)

DIR:=expensify
RECEIPT=$(DIR)/receipt.pdf
IMPORT=$(DIR)/import.csv
TXT=$(RECEIPT:.pdf=.txt)
ACTIVE_CODES:=active.txt
JQ_SCRIPT:=active.jq
TAG_CODES:=codes.csv

.DELETE_ON_ERROR: $(IMPORT)
.PHONY: all clean install
.INTERMEDIATE: $(TXT)

all: | $(RECEIPT) $(IMPORT)

$(DIR):
	test -d $@ || mkdir $@

$(RECEIPT): $(BILLS) | $(DIR)
	pdftk $^ cat output $@

$(TXT): $(RECEIPT) | $(DIR)
	pdftotext -layout $< $@

$(ACTIVE_CODES): policy.json $(JQ_SCRIPT)
	# XHR POST https://www.expensify.com/api?command=Policy_Get
	jq -rf $(JQ_SCRIPT) $< >$@

$(IMPORT): $(TAG_CODES) costya.php $(TXT) $(ACTIVE_CODES) | $(DIR)
	tail -n +2 $(TAG_CODES) | cut -d , -f2 | while read code; do \
		if ! egrep -q "^$$code\$$" $(ACTIVE_CODES); then \
			echo "Code $$code from $(TAG_CODES) not found in $(ACTIVE_CODES)" >&2; \
			exit 1; \
		fi; \
	done
	php -f costya.php -- $< <$(TXT) >$@

clean:
	-rm -f $(BILLS) $(DIR)/*

install:
	composer install --no-dev
