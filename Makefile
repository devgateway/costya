BILLS=$(wildcard *.pdf)

DIR:=expensify
RECEIPT=$(DIR)/receipt.pdf
IMPORT=$(DIR)/import.csv
TXT=$(RECEIPT:.pdf=.txt)

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

$(IMPORT): codes.csv costya.php $(TXT) | $(DIR)
	php -f costya.php -- $< <$(TXT) >$@

clean:
	-rm -f $(BILLS) $(DIR)/*

install:
	composer install --no-dev
