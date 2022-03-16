<?php

namespace uhi67\umvc;

interface SearchModel {
    /**
     * Must return a query composed for the parent model and containing the search data in its conditions.
     */
    public function search();
}
