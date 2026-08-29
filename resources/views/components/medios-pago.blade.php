@props(['id'])

{{--
    Selector de cómo paga un delivery o retiro.

    Espera que el x-data que lo contiene defina `metodo` y `pagaCon`, y aporta
    los dos campos que espera el backend: `metodo_pago` y `paga_con`.

    QR y transferencia van separados aunque para el cliente sean casi lo mismo:
    el cierre de caja los informa en líneas distintas (ver caja/cierre) y, si se
    mezclan, el arqueo no cuadra con lo que muestra el banco.
--}}
<div class="pays" style="grid-template-columns:repeat(2,1fr)">
    <button type="button" class="pay" :class="{ 'is-on': metodo === 'cash' }"
            @click="metodo = 'cash'"><x-icono nombre="cash" />Efectivo</button>
    <button type="button" class="pay" :class="{ 'is-on': metodo === 'qr' }"
            @click="metodo = 'qr'; pagaCon = null"><x-icono nombre="qr" />QR</button>
    <button type="button" class="pay" :class="{ 'is-on': metodo === 'transfer' }"
            @click="metodo = 'transfer'; pagaCon = null"><x-icono nombre="arrow" />Transferencia</button>
    <button type="button" class="pay" :class="{ 'is-on': metodo === 'debit' }"
            @click="metodo = 'debit'; pagaCon = null"><x-icono nombre="card" />Tarjeta</button>
</div>
<input type="hidden" name="metodo_pago" :value="metodo ?? ''">

<template x-if="metodo === 'cash'">
    <div class="field mt16">
        <label for="pagacon-{{ $id }}">Paga con</label>
        <input id="pagacon-{{ $id }}" class="inp" type="number" step="1" min="0"
               name="paga_con" x-model.number="pagaCon" inputmode="numeric"
               placeholder="50000">
    </div>
</template>
