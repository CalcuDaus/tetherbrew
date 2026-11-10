<?php
/**
 * Input validation helper
 * Pengganti Laravel Validator
 */

/**
 * Validate data against rules
 * @param array $data Data to validate (usually $_POST)
 * @param array $rules Validation rules ['field' => 'required|string|max:255']
 * @return array Array of errors, empty if valid
 */
function validate($data, $rules) {
    global $pdo;
    $errors = [];
    
    foreach ($rules as $field => $ruleString) {
        $rulesArray = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
        $value = $data[$field] ?? null;
        
        $customLabels = [
            'phone' => 'Nomor HP',
            'email' => 'Alamat Email',
            'name' => 'Nama Lengkap',
            'password' => 'Password',
        ];
        $fieldLabel = $customLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
        
        $isNumericRule = in_array('numeric', $rulesArray) || in_array('integer', $rulesArray);
        
        foreach ($rulesArray as $rule) {
            $param = null;
            if (strpos($rule, ':') !== false) {
                [$rule, $param] = explode(':', $rule, 2);
            }
            
            switch ($rule) {
                case 'required':
                    if ($value === null || $value === '' || $value === []) {
                        $errors[$field][] = "{$fieldLabel} wajib diisi.";
                    }
                    break;
                    
                case 'string':
                    if ($value !== null && $value !== '' && !is_string($value)) {
                        $errors[$field][] = "{$fieldLabel} harus berupa teks.";
                    }
                    break;
                    
                case 'numeric':
                    if ($value !== null && $value !== '' && !is_numeric($value)) {
                        $errors[$field][] = "{$fieldLabel} harus berupa angka.";
                    }
                    break;
                    
                case 'integer':
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
                        $errors[$field][] = "{$fieldLabel} harus berupa bilangan bulat.";
                    }
                    break;
                    
                case 'email':
                    if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $errors[$field][] = "{$fieldLabel} harus berupa format email yang valid.";
                    }
                    break;
                    
                case 'min':
                    if ($value !== null && $value !== '') {
                        if ($isNumericRule) {
                            if ($value < $param) $errors[$field][] = "{$fieldLabel} minimal {$param}.";
                        } else {
                            if (mb_strlen((string)$value) < $param) $errors[$field][] = "{$fieldLabel} minimal {$param} karakter.";
                        }
                    }
                    break;
                    
                case 'max':
                    if ($value !== null && $value !== '') {
                        if ($isNumericRule) {
                            if ($value > $param) $errors[$field][] = "{$fieldLabel} maksimal {$param}.";
                        } else {
                            if (mb_strlen((string)$value) > $param) $errors[$field][] = "{$fieldLabel} maksimal {$param} karakter.";
                        }
                    }
                    break;
                    
                case 'in':
                    $allowed = explode(',', $param);
                    if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
                        $errors[$field][] = "{$fieldLabel} harus salah satu dari: " . implode(', ', $allowed) . ".";
                    }
                    break;
                    
                case 'date':
                    if ($value !== null && $value !== '') {
                        $d = DateTime::createFromFormat('Y-m-d', $value);
                        if (!$d || $d->format('Y-m-d') !== $value) {
                            $errors[$field][] = "{$fieldLabel} harus berupa tanggal yang valid.";
                        }
                    }
                    break;
                    
                case 'unique':
                    if ($value !== null && $value !== '') {
                        $parts = explode(',', $param);
                        $table = $parts[0];
                        $column = $parts[1] ?? $field;
                        $exceptId = $parts[2] ?? null;
                        
                        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE {$column} = ?";
                        $params = [$value];
                        
                        if ($exceptId) {
                            $sql .= " AND id != ?";
                            $params[] = $exceptId;
                        }
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        if ($stmt->fetch()['cnt'] > 0) {
                            $errors[$field][] = "{$fieldLabel} sudah digunakan.";
                        }
                    }
                    break;
                    
                case 'exists':
                    if ($value !== null && $value !== '') {
                        $parts = explode(',', $param);
                        $table = $parts[0];
                        $column = $parts[1] ?? 'id';
                        
                        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM {$table} WHERE {$column} = ?");
                        $stmt->execute([$value]);
                        if ($stmt->fetch()['cnt'] == 0) {
                            $errors[$field][] = "{$fieldLabel} tidak ditemukan.";
                        }
                    }
                    break;
                    
                case 'confirmed':
                    $confirmField = $field . '_confirmation';
                    $confirmValue = $data[$confirmField] ?? null;
                    if ($value !== $confirmValue) {
                        $errors[$field][] = "Konfirmasi {$fieldLabel} tidak cocok.";
                    }
                    break;
                    
                case 'nullable':
                    // If value is null or empty, skip remaining rules
                    if ($value === null || $value === '') {
                        continue 3; // Skip to next field
                    }
                    break;
                    
                case 'array':
                    if ($value !== null && !is_array($value)) {
                        $errors[$field][] = "{$fieldLabel} harus berupa array.";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

/**
 * Get validation errors from session (set by redirect_back_with_errors)
 */
function validation_errors() {
    $errors = $_SESSION['validation_errors'] ?? [];
    unset($_SESSION['validation_errors']);
    return $errors;
}

/**
 * Check if there are validation errors
 */
function has_errors() {
    return !empty($_SESSION['validation_errors']);
}

/**
 * Render validation errors as HTML
 */
function render_errors($errors = null) {
    if ($errors === null) {
        $errors = validation_errors();
    }
    if (empty($errors)) return '';
    
    $html = '<div class="alert alert-error"><ul>';
    foreach ($errors as $field => $fieldErrors) {
        foreach ((array)$fieldErrors as $error) {
            $html .= '<li>' . e($error) . '</li>';
        }
    }
    $html .= '</ul></div>';
    
    return $html;
}

/**
 * Get first error for a specific field
 */
function field_error($field, $errors = []) {
    if (isset($errors[$field])) {
        $err = is_array($errors[$field]) ? $errors[$field][0] : $errors[$field];
        return '<span class="field-error">' . e($err) . '</span>';
    }
    return '';
}
