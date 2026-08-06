<?php

/*
|--------------------------------------------------------------------------
| Mensajes de validación
|--------------------------------------------------------------------------
|
| Laravel no trae traducciones al español. Sin este archivo, con APP_LOCALE=es
| el usuario ve la clave cruda ("validation.min.string") en vez del mensaje.
|
| Sólo están las reglas que la aplicación usa realmente.
|
*/

return [

    'accepted'    => 'Tenés que aceptar :attribute.',
    'after'       => ':Attribute tiene que ser posterior a :date.',
    'array'       => ':Attribute tiene que ser una lista.',
    'before'      => ':Attribute tiene que ser anterior a :date.',
    'boolean'     => ':Attribute tiene que ser verdadero o falso.',
    'confirmed'   => ':Attribute no coincide con la confirmación.',
    'date'        => ':Attribute no es una fecha válida.',
    'different'   => ':Attribute y :other tienen que ser distintos.',
    'email'       => ':Attribute tiene que ser un correo válido.',
    // Los nombres de campo llevan artículo ("el motivo"), así que los mensajes
    // se redactan para que :Attribute funcione como sujeto de la oración.
    'exists'      => ':Attribute que elegiste no existe.',
    'in'          => ':Attribute que elegiste no es válido.',
    'integer'     => ':Attribute tiene que ser un número entero.',
    'not_in'      => ':Attribute que elegiste no es válido.',
    'numeric'     => ':Attribute tiene que ser un número.',
    'required'    => 'Falta completar :attribute.',
    'string'      => ':Attribute tiene que ser texto.',
    'unique'      => ':Attribute ya está en uso.',

    'min' => [
        'array'   => ':Attribute tiene que tener al menos :min elementos.',
        'file'    => ':Attribute tiene que pesar al menos :min kilobytes.',
        'numeric' => ':Attribute no puede ser menor que :min.',
        'string'  => ':Attribute tiene que tener al menos :min caracteres.',
    ],

    'max' => [
        'array'   => ':Attribute no puede tener más de :max elementos.',
        'file'    => ':Attribute no puede pesar más de :max kilobytes.',
        'numeric' => ':Attribute no puede ser mayor que :max.',
        'string'  => ':Attribute no puede tener más de :max caracteres.',
    ],

    /*
    | Nombres legibles. Las columnas están en inglés por convención de Laravel,
    | pero el mensaje que ve el usuario tiene que estar en español.
    */
    'attributes' => [
        'email'           => 'el usuario',
        'password'        => 'la clave',
        'name'            => 'el nombre',
        'price'           => 'el precio',
        'cost'            => 'el costo',
        'amount'          => 'el importe',
        'total'           => 'el total',
        'concept'         => 'el concepto',
        'motivo'          => 'el motivo',
        'reason'          => 'el motivo',
        'cantidad'        => 'la cantidad',
        'unidad'          => 'la unidad',
        'contenido'       => 'el contenido del envase',
        'rinde'           => 'cuántas unidades rinde',
        'telefono'        => 'el teléfono',
        'nombre'          => 'el nombre',
        'calle'           => 'la dirección',
        'detalle'         => 'el detalle',
        'notas'           => 'las notas',
        'porcentaje'      => 'el porcentaje',
        'redondeo'        => 'el redondeo',
        'category_id'     => 'la categoría',
        'ingredient_id'   => 'el insumo',
        'zone_id'         => 'la zona',
        'driver_id'       => 'el repartidor',
        'mozo_id'         => 'el mozo',
        'table_rate_id'   => 'la tarifa',
        'metodo_pago'     => 'el medio de pago',
        'method'          => 'el medio de pago',
        'opening_float'   => 'el fondo inicial',
        'entregado'       => 'lo que entregás',
        'contado'         => 'lo contado',
        'guests'          => 'la cantidad de personas',
        'minutos_atras'   => 'hace cuánto arrancó',
        'reference'       => 'la referencia',
        'difference_note' => 'la explicación',
        'se_consumio'     => 'si se consumió',
        'estado'          => 'el estado',
        'base_unit'       => 'la unidad de medida',
        'min_stock'       => 'el stock mínimo',
        'area'            => 'el área',
        'lineas'          => 'los productos',
    ],

    'custom' => [
        'lineas' => [
            'required' => 'Agregá al menos un producto.',
            'min'      => 'Agregá al menos un producto.',
        ],
    ],

];
