BILLS=$(wildcard *.pdf)

DIR:=expensify
RECEIPT=$(DIR)/receipt.pdf
CSV=$(DIR)/import.csv

.DELETE_ON_ERROR: $(CSV)
.PHONY: all clean install

all: | $(RECEIPT) $(CSV)

$(DIR):
	test -d $@ || mkdir $@

$(RECEIPT): $(BILLS) | expensify
	pdftk $^ cat output $@

$(CSV): billing-codes.json costya.php | expensify
	@if [ -z "$(DATE)" -o -z "$(TOTAL)" ]; then \
	  echo "Invoice DATE and TOTAL are required, e.g.:" >&2; \
	  echo "$(MAKE) DATE=2021-01-15 TOTAL=123.45+12.42 $@" >&2; \
	  exit 1; \
	fi
	php -f costya.php -- -d $(DATE) -b $< -t "$$(echo "$(TOTAL)" | bc)" >$@

clean:
	-rm -f $(BILLS) $(DIR)/*

install:
	composer install --no-dev
