# Author: Dominik Harmim <xharmi00@stud.fit.vutbr.cz>

PACK := xharmi00.tgz
PACK_TMP_DIR := pack_tmp

IS_IT_OK_DIR := is_it_ok_test
IS_IT_OK_SCRIPT := ./is_it_ok.sh
TASK := 0

SRCS := php/*.php python/*.py


.PHONY: pack
pack: clean $(PACK)

$(PACK):
	mkdir -p $(PACK_TMP_DIR)
	cp $(SRCS) $(PACK_TMP_DIR)
	tar -czf $@ -C $(PACK_TMP_DIR) .
	rm -rf $(PACK_TMP_DIR)

.PHONY: clean_pack
clean_pack:
	rm -rf $(PACK) $(PACK_TMP_DIR)


.PHONY: is_it_ok
is_it_ok: $(PACK) $(IS_IT_OK_SCRIPT) clean_is_it_ok
	chmod +x $(IS_IT_OK_SCRIPT)
ifeq ($(TASK), 0)
	$(IS_IT_OK_SCRIPT) $(PACK) $(IS_IT_OK_DIR)
else
	$(IS_IT_OK_SCRIPT) $(PACK) $(IS_IT_OK_DIR) $(TASK)
endif

.PHONY: clean_is_it_ok
clean_is_it_ok:
	rm -rf $(IS_IT_OK_DIR)


.PHONY: clean
clean: clean_pack clean_is_it_ok
