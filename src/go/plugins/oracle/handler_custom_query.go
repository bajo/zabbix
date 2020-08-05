/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

package oracle

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
)

const keyCustomQuery = "oracle.custom.query"
const customQueryMinParams = 1

// customQueryHandler TODO: add description.
func customQueryHandler(ctx context.Context, conn OraClient, params []string) (interface{}, error) {
	if len(params) < customQueryMinParams {
		return nil, errorInvalidParams
	}

	queryName := params[0]
	queryArgs := make([]interface{}, len(params[1:]))

	for i, v := range params[1:] {
		queryArgs[i] = v
	}

	rows, err := conn.QueryByName(ctx, queryName, queryArgs...)
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	// JSON marshaling
	var data []string

	columns, err := rows.Columns()
	if err != nil {
		return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
	}

	values := make([]interface{}, len(columns))
	valuePointers := make([]interface{}, len(values))

	for i := range values {
		valuePointers[i] = &values[i]
	}

	results := make(map[string]interface{})

	for rows.Next() {
		err = rows.Scan(valuePointers...)
		if err != nil {
			return nil, fmt.Errorf("%w (%s)", errorCannotFetchData, err.Error())
		}

		for i, value := range values {
			results[columns[i]] = value
		}

		jsonRes, _ := json.Marshal(results)
		data = append(data, strings.TrimSpace(string(jsonRes)))
	}

	return "[" + strings.Join(data, ",") + "]", nil
}
