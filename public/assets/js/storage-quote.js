var numUSD = new Intl.NumberFormat("en-US", {
  style: "currency",
  currency: "PHP",
});
// Disable btns 162021
// $('#unit1').attr('disabled','disabled');
// $('#unit2').attr('disabled','disabled');
// $('#unit3').attr('disabled','disabled');
// $('#unit4').attr('disabled','disabled');
//   Price per unit disabled textbox

// unit dropdown textbox
$("#selsr1").hide();
$("#unit1_print").text("-");
$("#unit2_print").text("-");
$("#unit3_print").text("-");
$("#unit4_print").text("-");
// total storage fee
$("#total_storage_fee").text("-");
// unit price
$("#unit1_price").text("-");
$("#unit2_price").text("-");
$("#unit3_price").text("-");
$("#unit4_price").text("-");
//Insurance Storage Unit
$("#unit1_ins").text("-");
$("#unit2_ins").text("-");
$("#unit3_ins").text("-");
$("#unit4_ins").text("-");
//Size SQM Storage Unit
$("#sqm1_print").text("-");
$("#sqm2_print").text("-");
$("#sqm3_print").text("-");
$("#sqm4_print").text("-");

$("#selsr2").hide();
$("#selsr3").hide();
$("#selsr4").hide();
// unit
$("#unit2").hide();
$("#unit3").hide();
$("#unit4").hide();
$("#unit2_label").hide();
$("#unit3_label").hide();
$("#unit4_label").hide();
$("#insurance_unit2").hide();
$("#insurance_unit3").hide();
$("#insurance_unit4").hide();
$("#insurance_unit2_label").hide();
$("#insurance_unit3_label").hide();
$("#insurance_unit4_label").hide();
// unit 2
$("#unit2").change(function () {
  $("#unit3_label").show();
  $("#unit3").show();
  $("#insurance_unit3_label").show();
  $("#insurance_unit3").show();
});
// unit 3
$("#unit3").change(function () {
  $("#unit4_label").show();
  $("#unit4").show();
  $("#insurance_unit4_label").show();
  $("#insurance_unit4").show();
});

// ------------------------------------------------------ //
// unit 1 queries
$("#unit1").change(function () {
  // show or hide dropbox
  var unit1 = $("#unit1").val();
  var lo_code = $("#lo_code").val();
  if (unit1 != "") {
    // $('#selsr1').show();
    $("#unit2_label").show();
    $("#unit2").show();
    $("#insurance_unit2_label").show();
    $("#insurance_unit2").show();
    $("#unit1_print").text(unit1);
    $("#unit1_print_hdn").val(unit1);
    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;
  } else {
    $("#selsr1").hide();
    $("#unit2_label").hide();
    $("#unit2").hide();
    $("#insurance_unit2_label").hide();
    $("#insurance_unit2").hide();
    $("#unit1_print").text("-");
    $("#unit1_print_hdn").val("");

    // added sep 16, 2020
    $("#unit1_price").text("-");
    $("#unit1_price_hdn").val("");
    $("#unit1_pricenet_hdn").val("");
    $("#unit1_ins").text("");
    $("#unit1_ins_val").text("");
    $("#unit1_ins_hdn").val("");
    $("#insurance_unit1").prop("selectedIndex", 0);
    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;

    var u1p = $("#unit1_price_hdn").val();
    var u2p = $("#unit2_price_hdn").val();
    var u3p = $("#unit3_price_hdn").val();
    var u4p = $("#unit4_price_hdn").val();
    var remaining_storage_fee = u1p * 1 + u2p * 1 + u3p * 1 + u4p * 1;
    document.getElementById("total_storage_fee").innerHTML = numUSD.format(
      remaining_storage_fee
    );
    $("#total_storage_fee_final").val(remaining_storage_fee);
    $("#late_fee").val(remaining_storage_fee * 0.1);
  }

  document.getElementById("units_ins_val_total").innerHTML = numUSD.format(sum);
  $("#total_ins_final").val(sum);

  $.ajax({
    url:
      (window.STORAGE_QUOTE_UNITS_URL || "/api/quotation-builder/storage-quotes/units") + "?action=unit1&unit1=" +
      unit1 +
      "&lo_code=" +
      lo_code,
    beforeSend: function () {
      $("#unit1_loading").prop("hidden", false);
    },
    complete: function () {
      $("#unit1_loading").prop("hidden", true);
    },
    success: function (response) {
      if (response != "") {
        var numUSD = new Intl.NumberFormat("en-US", {
          style: "currency",
          currency: "PHP",
        });
        // alert(response);
        $("#selsr1").val(response);
        // Disable btns 162021
        $("#insurance_unit1").removeAttr("disabled");

        var sr = $("#selsr1").val();
        var srArr = sr.toString().split("|");
        $("#unit1").val(srArr[0]);
        $("#selsr1").hide();
        $("#unit1_print").text(srArr[0]);
        $("#unit1_print_hdn").val(srArr[0]);
        $("#unit1_price").text(srArr[1]);
        $("#unit1_price_hdn").val(srArr[1]);
        $("#sqm1_print").text(srArr[3]);
        $("#sqm1_print_hdn").val(srArr[3]);
        document.getElementById("total_storage_fee").innerHTML = numUSD.format(
          srArr[1]
        );
        $("#total_storage_fee_final").val(srArr[1]);
        $("#late_fee").val(srArr[1] * 0.1);
        // Unit Price Net Tax
        $("#unit1_pricenet_hdn").val(srArr[2]);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // deposit no tax
        document.getElementById("deposit_notax").innerHTML =
          numUSD.format(total_pricenet);
        $("#deposit_notax_hdn").val(total_pricenet);

        // Final total value
        var insurance = $("#total_ins_final").val();
        var current_partial = $("#partial_hdn").val();
        var storage_period = $("#initial_period_hdn").val();
        var storage_fee = $("#total_storage_fee_final").val();
        var com1 = storage_period * insurance;
        var com2 = current_partial * insurance;
        var computation = com1 + com2;
        document.getElementById("ins_total").innerHTML =
          numUSD.format(computation);
        $("#ins_total_hdn").val(computation);
        var com3 = storage_period * 1 + current_partial * 1;
        var total_storage_fee = storage_fee * com3;
        document.getElementById("final_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#final_storage_fee_hdn").val(total_storage_fee);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        var admin_fee = $("#admin_fee_hdn").val();
        var adj_nonvat = $("#adjustments_nonvat").val();
        var adjustments1 = $("#adjustments1").val();
        var adjustments2 = $("#adjustments2").val();
        var adjustments3 = $("#adjustments3").val();
        var adjustments4 = $("#adjustments4").val();
        var total_value =
          total_pricenet * 1 +
          admin_fee * 1 +
          computation * 1 +
          adj_nonvat * 1 +
          adjustments1 * 1 +
          adjustments2 * 1 +
          adjustments3 * 1 +
          adjustments4 * 1 +
          total_storage_fee;
        $("#total_final_hdn").val(total_value);
        document.getElementById("total_final").innerHTML =
          numUSD.format(total_value);

        // memo vat
        // tax exempt
        var tax_exempt = $("#tax_exempt").val;
        // total
        var total = $("#total_final_hdn").val();
        // non vat
        var non_vat = $("#adjustments_nonvat").val();
        // Deposit (no tax)
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var deposit_notax =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // witholding tax
        var withholding_tax = $("#withholding_tax").val();
        var tax_exempt = $("#tax_exempt").val();
        if (withholding_tax == "Yes") {
          var withhold = 0.02;
          if (tax_exempt == "Yes") {
            var vat = 1;
          } else {
            var vat = 1.12;
          }
          var tot_withhold =
            computation * 1 +
            total_storage_fee * 1 +
            admin_fee * 1 +
            adjustments1 * 1 +
            adjustments2 * 1 +
            adjustments3 * 1 +
            adjustments4 * 1;
          var f_withhold = tot_withhold * withhold;
          var final_withhold = f_withhold / vat;
        } else {
          var final_withhold = 0;
        }
        // computation
        if (tax_exempt == "Yes") {
          $("#memo_vat").text("-");
          $("#memo_vat_hdn").val(0);
        } else {
          var withholding_tax_hdn = $("#withholding_tax_hdn").val();
          var x = 1;
          var com1 =
            ((total * 1 +
              withholding_tax_hdn * 1 -
              non_vat * 1 -
              deposit_notax * 1) /
              1.12) *
            0.12 *
            (x * 1);
          document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
          $("#memo_vat_hdn").val(com1);
        }
        // document.getElementById('selsr1').hidden = false;
      } else {
        $("#selsr1").val("");
        // document.getElementById('selsr1').hidden = true;
      }
    },
  });

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);
});

// ------------------------------------------------------ //
// unit 2 queries
$("#unit2").change(function () {
  // show or hide dropbox
  var unit2 = $("#unit2").val();
  var lo_code = $("#lo_code").val();
  if (unit2 != "") {
    // $('#selsr2').show();
    $("#unit2_label").show();
    $("#unit2").show();
    $("#insurance_unit2_label").show();
    $("#insurance_unit2").show();
    $("#unit2_print").text(unit2);
    $("#unit2_print_hdn").val(unit2);

    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;
  } else {
    $("#selsr2").hide();
    $("#unit2_label").hide();
    $("#unit2").hide();
    $("#insurance_unit2_label").hide();
    $("#insurance_unit2").hide();
    $("#unit2_print").text("-");
    $("#unit2_print_hdn").val("");

    // added sep 16, 2020
    $("#unit2_price").text("-");
    $("#unit2_price_hdn").val("");
    $("#unit2_pricenet_hdn").val("");
    $("#unit2_ins").text("");
    $("#unit2_ins_val").text("");
    $("#unit2_ins_hdn").val("");
    $("#insurance_unit2").prop("selectedIndex", 0);
    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;

    var u1p = $("#unit1_price_hdn").val();
    var u2p = $("#unit2_price_hdn").val();
    var u3p = $("#unit3_price_hdn").val();
    var u4p = $("#unit4_price_hdn").val();
    var remaining_storage_fee = u1p * 1 + u2p * 1 + u3p * 1 + u4p * 1;
    document.getElementById("total_storage_fee").innerHTML = numUSD.format(
      remaining_storage_fee
    );
    $("#total_storage_fee_final").val(remaining_storage_fee);
    $("#late_fee").val(remaining_storage_fee * 0.1);
  }

  document.getElementById("units_ins_val_total").innerHTML = numUSD.format(sum);
  $("#total_ins_final").val(sum);

  $.ajax({
    url:
      (window.STORAGE_QUOTE_UNITS_URL || "/api/quotation-builder/storage-quotes/units") + "?action=unit2&unit2=" +
      unit2 +
      "&lo_code=" +
      lo_code,
    beforeSend: function () {
      $("#unit2_loading").prop("hidden", false);
    },
    complete: function () {
      $("#unit2_loading").prop("hidden", true);
    },
    success: function (response) {
      if (response != "") {
        $("#selsr2").val(response);
        // document.getElementById('selsr2').hidden = false;
        var numUSD = new Intl.NumberFormat("en-US", {
          style: "currency",
          currency: "PHP",
        });
        // Disable btns 162021
        $("#insurance_unit2").removeAttr("disabled");

        var sr = $("#selsr2").val();
        var srArr = sr.toString().split("|");
        $("#unit2").val(srArr[0]);
        $("#selsr2").hide();
        $("#unit2_print").text(srArr[0]);
        $("#unit2_print_hdn").val(srArr[0]);
        var unit1_price = $("#unit1_price_hdn").val();
        var unit2_price = srArr[1];
        $("#unit2_price").text(unit2_price);
        $("#unit2_price_hdn").val(unit2_price);
        $("#sqm2_print").text(srArr[3]);
        $("#sqm2_print_hdn").val(srArr[3]);
        var total_storage_fee = (unit1_price * 1 + unit2_price * 1) / 1;
        // $('#total_storage_fee').text(total_storage_fee);
        document.getElementById("total_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#total_storage_fee_final").val(total_storage_fee);
        $("#late_fee").val(total_storage_fee * 0.1);

        // Unit Price Net Tax
        $("#unit2_pricenet_hdn").val(srArr[2]);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // deposit no tax
        document.getElementById("deposit_notax").innerHTML =
          numUSD.format(total_pricenet);
        $("#deposit_notax_hdn").val(total_pricenet);

        // Final total value
        var insurance = $("#total_ins_final").val();
        var current_partial = $("#partial_hdn").val();
        var storage_period = $("#initial_period_hdn").val();
        var storage_fee = $("#total_storage_fee_final").val();
        var com1 = storage_period * insurance;
        var com2 = current_partial * insurance;
        var computation = com1 + com2;
        document.getElementById("ins_total").innerHTML =
          numUSD.format(computation);
        $("#ins_total_hdn").val(computation);
        var com3 = storage_period * 1 + current_partial * 1;
        var total_storage_fee = storage_fee * com3;
        document.getElementById("final_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#final_storage_fee_hdn").val(total_storage_fee);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        var admin_fee = $("#admin_fee_hdn").val();
        var adj_nonvat = $("#adjustments_nonvat").val();
        var adjustments1 = $("#adjustments1").val();
        var adjustments2 = $("#adjustments2").val();
        var adjustments3 = $("#adjustments3").val();
        var adjustments4 = $("#adjustments4").val();
        var reduction = $("#reduction_hdn").val();
        var total_value =
          total_pricenet * 1 +
          admin_fee * 1 +
          computation * 1 +
          adj_nonvat * 1 +
          adjustments1 * 1 +
          adjustments2 * 1 +
          adjustments3 * 1 +
          adjustments4 * 1 +
          total_storage_fee * 1 -
          reduction * 1;
        $("#total_final_hdn").val(total_value);
        document.getElementById("total_final").innerHTML =
          numUSD.format(total_value);

        // memo vat
        // tax exempt
        var tax_exempt = $("#tax_exempt").val;
        // total
        var total = $("#total_final_hdn").val();
        // non vat
        var non_vat = $("#adjustments_nonvat").val();
        // Deposit (no tax)
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var deposit_notax =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // witholding tax
        var withholding_tax = $("#withholding_tax").val();
        var tax_exempt = $("#tax_exempt").val();
        if (withholding_tax == "Yes") {
          var withhold = 0.02;
          if (tax_exempt == "Yes") {
            var vat = 1;
          } else {
            var vat = 1.12;
          }
          var tot_withhold =
            computation * 1 +
            total_storage_fee * 1 +
            admin_fee * 1 +
            adjustments1 * 1 +
            adjustments2 * 1 +
            adjustments3 * 1 +
            adjustments4 * 1;
          var f_withhold = tot_withhold * withhold;
          var final_withhold = f_withhold / vat;
        } else {
          var final_withhold = 0;
        }
        // computation
        if (tax_exempt == "Yes") {
          $("#memo_vat").text("-");
          $("#memo_vat_hdn").val(0);
        } else {
          var withholding_tax_hdn = $("#withholding_tax_hdn").val();
          var x = 1;
          var com1 =
            ((total * 1 +
              withholding_tax_hdn * 1 -
              non_vat * 1 -
              deposit_notax * 1) /
              1.12) *
            0.12 *
            (x * 1);
          document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
          $("#memo_vat_hdn").val(com1);
        }
      } else {
        $("#selsr2").val("");
        document.getElementById("selsr2").hidden = true;
      }
    },
  });

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);
});

// ------------------------------------------------------ //
// unit 3 queries
$("#unit3").change(function () {
  // show or hide dropbox
  var unit3 = $("#unit3").val();
  var lo_code = $("#lo_code").val();

  if (unit3 != "") {
    // $('#selsr3').show();
    $("#unit3_label").show();
    $("#unit3").show();
    $("#insurance_unit3_label").show();
    $("#insurance_unit3").show();
    $("#unit3_print").text(unit3);
    $("#unit3_print_hdn").val(unit3);

    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;
  } else {
    $("#selsr3").hide();
    $("#unit3_label").hide();
    $("#unit3").hide();
    $("#insurance_unit3_label").hide();
    $("#insurance_unit3").hide();
    $("#unit3_print").text("-");
    $("#unit3_print_hdn").val("");

    // added sep 16, 2020
    $("#unit3_price").text("-");
    $("#unit3_price_hdn").val("");
    $("#unit3_pricenet_hdn").val("");
    $("#unit3_ins").text("");
    $("#unit3_ins_val").text("");
    $("#unit3_ins_hdn").val("");
    $("#insurance_unit3").prop("selectedIndex", 0);
    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;

    var u1p = $("#unit1_price_hdn").val();
    var u2p = $("#unit2_price_hdn").val();
    var u3p = $("#unit3_price_hdn").val();
    var u4p = $("#unit4_price_hdn").val();
    var remaining_storage_fee = u1p * 1 + u2p * 1 + u3p * 1 + u4p * 1;
    document.getElementById("total_storage_fee").innerHTML = numUSD.format(
      remaining_storage_fee
    );
    $("#total_storage_fee_final").val(remaining_storage_fee);
    $("#late_fee").val(remaining_storage_fee * 0.1);
  }

  document.getElementById("units_ins_val_total").innerHTML = numUSD.format(sum);
  $("#total_ins_final").val(sum);

  $.ajax({
    url:
      (window.STORAGE_QUOTE_UNITS_URL || "/api/quotation-builder/storage-quotes/units") + "?action=unit3&unit3=" +
      unit3 +
      "&lo_code=" +
      lo_code,
    beforeSend: function () {
      $("#unit3_loading").prop("hidden", false);
    },
    complete: function () {
      $("#unit3_loading").prop("hidden", true);
    },
    success: function (response) {
      if (response != "") {
        $("#selsr3").val(response);
        // document.getElementById('selsr3').hidden = false;
        var numUSD = new Intl.NumberFormat("en-US", {
          style: "currency",
          currency: "PHP",
        });
        // Disable btns 162021
        $("#insurance_unit3").removeAttr("disabled");

        var sr = $("#selsr3").val();
        var srArr = sr.toString().split("|");
        $("#unit3").val(srArr[0]);
        $("#selsr3").hide();
        $("#unit3_print").text(srArr[0]);
        $("#unit3_print_hdn").val(srArr[0]);
        var unit1_price = $("#unit1_price_hdn").val();
        var unit2_price = $("#unit2_price_hdn").val();
        var unit3_price = srArr[1];
        $("#unit3_price").text(unit3_price);
        $("#unit3_price_hdn").val(unit3_price);
        $("#sqm3_print").text(srArr[3]);
        $("#sqm3_print_hdn").val(srArr[3]);
        var total_storage_fee =
          (unit1_price * 1 + unit2_price * 1 + unit3_price * 1) / 1;
        // $('#total_storage_fee').text(total_storage_fee);
        document.getElementById("total_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#total_storage_fee_final").val(total_storage_fee);
        $("#late_fee").val(total_storage_fee * 0.1);

        // Unit Price Net Tax
        $("#unit3_pricenet_hdn").val(srArr[2]);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // deposit no tax
        document.getElementById("deposit_notax").innerHTML =
          numUSD.format(total_pricenet);
        $("#deposit_notax_hdn").val(total_pricenet);

        // Final total value
        var insurance = $("#total_ins_final").val();
        var current_partial = $("#partial_hdn").val();
        var storage_period = $("#initial_period_hdn").val();
        var storage_fee = $("#total_storage_fee_final").val();
        var com1 = storage_period * insurance;
        var com2 = current_partial * insurance;
        var computation = com1 + com2;
        document.getElementById("ins_total").innerHTML =
          numUSD.format(computation);
        $("#ins_total_hdn").val(computation);
        var com3 = storage_period * 1 + current_partial * 1;
        var total_storage_fee = storage_fee * com3;
        document.getElementById("final_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#final_storage_fee_hdn").val(total_storage_fee);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        var admin_fee = $("#admin_fee_hdn").val();
        var adj_nonvat = $("#adjustments_nonvat").val();
        var adjustments1 = $("#adjustments1").val();
        var adjustments2 = $("#adjustments2").val();
        var adjustments3 = $("#adjustments3").val();
        var adjustments4 = $("#adjustments4").val();
        var reduction = $("#reduction_hdn").val();
        var total_value =
          total_pricenet * 1 +
          admin_fee * 1 +
          computation * 1 +
          adj_nonvat * 1 +
          adjustments1 * 1 +
          adjustments2 * 1 +
          adjustments3 * 1 +
          adjustments4 * 1 +
          total_storage_fee * 1 -
          reduction * 1;
        $("#total_final_hdn").val(total_value);
        document.getElementById("total_final").innerHTML =
          numUSD.format(total_value);

        // memo vat
        // tax exempt
        var tax_exempt = $("#tax_exempt").val;
        // total
        var total = $("#total_final_hdn").val();
        // non vat
        var non_vat = $("#adjustments_nonvat").val();
        // Deposit (no tax)
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var deposit_notax =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // witholding tax
        var withholding_tax = $("#withholding_tax").val();
        var tax_exempt = $("#tax_exempt").val();
        if (withholding_tax == "Yes") {
          var withhold = 0.02;
          if (tax_exempt == "Yes") {
            var vat = 1;
          } else {
            var vat = 1.12;
          }
          var tot_withhold =
            computation * 1 +
            total_storage_fee * 1 +
            admin_fee * 1 +
            adjustments1 * 1 +
            adjustments2 * 1 +
            adjustments3 * 1 +
            adjustments4 * 1;
          var f_withhold = tot_withhold * withhold;
          var final_withhold = f_withhold / vat;
        } else {
          var final_withhold = 0;
        }
        // computation
        if (tax_exempt == "Yes") {
          $("#memo_vat").text("-");
          $("#memo_vat_hdn").val(0);
        } else {
          var withholding_tax_hdn = $("#withholding_tax_hdn").val();
          var x = 1;
          var com1 =
            ((total * 1 +
              withholding_tax_hdn * 1 -
              non_vat * 1 -
              deposit_notax * 1) /
              1.12) *
            0.12 *
            (x * 1);
          document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
          $("#memo_vat_hdn").val(com1);
        }
      } else {
        $("#selsr3").val("");
        document.getElementById("selsr3").hidden = true;
      }
    },
  });

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);
});
// ------------------------------------------------------ //
// unit 4 queries
$("#unit4").change(function () {
  // show or hide dropbox
  var unit4 = $("#unit4").val();
  var lo_code = $("#lo_code").val();
  if (unit4 != "") {
    // $('#selsr4').show();
    $("#unit4_label").show();
    $("#unit4").show();
    $("#insurance_unit4_label").show();
    $("#insurance_unit4").show();
    $("#unit4_print").text(unit4);
    $("#unit4_print_hdn").val(unit4);

    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;
  } else {
    $("#selsr4").hide();
    $("#unit4_label").hide();
    $("#unit4").hide();
    $("#insurance_unit4_label").hide();
    $("#insurance_unit4").hide();
    $("#unit4_print").text("-");
    $("#unit4_print_hdn").val("");

    // added sep 16, 2020
    $("#unit4_price").text("-");
    $("#unit4_price_hdn").val("");
    $("#unit4_pricenet_hdn").val("");
    $("#unit4_ins").text("");
    $("#unit4_ins_val").text("");
    $("#unit4_ins_hdn").val("");
    $("#insurance_unit4").prop("selectedIndex", 0);
    var unit1_val = $("#unit1_ins_hdn").val();
    var unit2_val = $("#unit2_ins_hdn").val();
    var unit3_val = $("#unit3_ins_hdn").val();
    var unit4_val = $("#unit4_ins_hdn").val();
    var sum =
      (unit1_val * 1 + unit2_val * 1 + unit3_val * 1 + unit4_val * 1) / 1;

    var u1p = $("#unit1_price_hdn").val();
    var u2p = $("#unit2_price_hdn").val();
    var u3p = $("#unit3_price_hdn").val();
    var u4p = $("#unit4_price_hdn").val();
    var remaining_storage_fee = u1p * 1 + u2p * 1 + u3p * 1 + u4p * 1;
    document.getElementById("total_storage_fee").innerHTML = numUSD.format(
      remaining_storage_fee
    );
    $("#total_storage_fee_final").val(remaining_storage_fee);
    $("#late_fee").val(remaining_storage_fee * 0.1);
  }

  document.getElementById("units_ins_val_total").innerHTML = numUSD.format(sum);
  $("#total_ins_final").val(sum);

  $.ajax({
    url:
      (window.STORAGE_QUOTE_UNITS_URL || "/api/quotation-builder/storage-quotes/units") + "?action=unit4&unit4=" +
      unit4 +
      "&lo_code=" +
      lo_code,
    beforeSend: function () {
      $("#unit4_loading").prop("hidden", false);
    },
    complete: function () {
      $("#unit4_loading").prop("hidden", true);
    },
    success: function (response) {
      if (response != "") {
        $("#selsr4").val(response);
        // document.getElementById('selsr4').hidden = false;
        var numUSD = new Intl.NumberFormat("en-US", {
          style: "currency",
          currency: "PHP",
        });
        // Disable btns 162021
        $("#insurance_unit4").removeAttr("disabled");

        var sr = $("#selsr4").val();
        var srArr = sr.toString().split("|");
        $("#unit4").val(srArr[0]);
        $("#selsr4").hide();
        $("#unit4_print").text(srArr[0]);
        $("#unit4_print_hdn").val(srArr[0]);
        $("#sqm4_print").text(srArr[3]);
        $("#sqm4_print_hdn").val(srArr[3]);
        var unit1_price = $("#unit1_price_hdn").val();
        var unit2_price = $("#unit2_price_hdn").val();
        var unit3_price = $("#unit3_price_hdn").val();
        var unit4_price = srArr[1];
        $("#unit4_price").text(unit4_price);
        $("#unit4_price_hdn").val(unit4_price);
        var total_storage_fee =
          (unit1_price * 1 +
            unit2_price * 1 +
            unit3_price * 1 +
            unit4_price * 1) /
          1;
        // $('#total_storage_fee').text(total_storage_fee);
        document.getElementById("total_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#total_storage_fee_final").val(total_storage_fee);
        $("#late_fee").val(total_storage_fee * 0.1);

        // Unit Price Net Tax
        $("#unit4_pricenet_hdn").val(srArr[2]);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // deposit no tax
        document.getElementById("deposit_notax").innerHTML =
          numUSD.format(total_pricenet);
        $("#deposit_notax_hdn").val(total_pricenet);

        // Final total value
        var insurance = $("#total_ins_final").val();
        var current_partial = $("#partial_hdn").val();
        var storage_period = $("#initial_period_hdn").val();
        var storage_fee = $("#total_storage_fee_final").val();
        var com1 = storage_period * insurance;
        var com2 = current_partial * insurance;
        var computation = com1 + com2;
        document.getElementById("ins_total").innerHTML =
          numUSD.format(computation);
        $("#ins_total_hdn").val(computation);
        var com3 = storage_period * 1 + current_partial * 1;
        var total_storage_fee = storage_fee * com3;
        document.getElementById("final_storage_fee").innerHTML =
          numUSD.format(total_storage_fee);
        $("#final_storage_fee_hdn").val(total_storage_fee);
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var total_pricenet =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        var admin_fee = $("#admin_fee_hdn").val();
        var adj_nonvat = $("#adjustments_nonvat").val();
        var adjustments1 = $("#adjustments1").val();
        var adjustments2 = $("#adjustments2").val();
        var adjustments3 = $("#adjustments3").val();
        var adjustments4 = $("#adjustments4").val();
        var reduction = $("#reduction_hdn").val();
        var total_value =
          total_pricenet * 1 +
          admin_fee * 1 +
          computation * 1 +
          adj_nonvat * 1 +
          adjustments1 * 1 +
          adjustments2 * 1 +
          adjustments3 * 1 +
          adjustments4 * 1 +
          total_storage_fee * 1 -
          reduction * 1;
        $("#total_final_hdn").val(total_value);
        document.getElementById("total_final").innerHTML =
          numUSD.format(total_value);

        // memo vat
        // tax exempt
        var tax_exempt = $("#tax_exempt").val;
        // total
        var total = $("#total_final_hdn").val();
        // non vat
        var non_vat = $("#adjustments_nonvat").val();
        // Deposit (no tax)
        var unit1_pricenet = $("#unit1_pricenet_hdn").val();
        var unit2_pricenet = $("#unit2_pricenet_hdn").val();
        var unit3_pricenet = $("#unit3_pricenet_hdn").val();
        var unit4_pricenet = $("#unit4_pricenet_hdn").val();
        var deposit_notax =
          (unit1_pricenet * 1 +
            unit2_pricenet * 1 +
            unit3_pricenet * 1 +
            unit4_pricenet * 1) /
          1;
        // witholding tax
        var withholding_tax = $("#withholding_tax").val();
        var tax_exempt = $("#tax_exempt").val();
        if (withholding_tax == "Yes") {
          var withhold = 0.02;
          if (tax_exempt == "Yes") {
            var vat = 1;
          } else {
            var vat = 1.12;
          }
          var tot_withhold =
            computation * 1 +
            total_storage_fee * 1 +
            admin_fee * 1 +
            adjustments1 * 1 +
            adjustments2 * 1 +
            adjustments3 * 1 +
            adjustments4 * 1;
          var f_withhold = tot_withhold * withhold;
          var final_withhold = f_withhold / vat;
        } else {
          var final_withhold = 0;
        }
        // computation
        if (tax_exempt == "Yes") {
          $("#memo_vat").text("-");
          $("#memo_vat_hdn").val(0);
        } else {
          var withholding_tax_hdn = $("#withholding_tax_hdn").val();
          var x = 1;
          var com1 =
            ((total * 1 +
              withholding_tax_hdn * 1 -
              non_vat * 1 -
              deposit_notax * 1) /
              1.12) *
            0.12 *
            (x * 1);
          document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
          $("#memo_vat_hdn").val(com1);
        }
      } else {
        $("#selsr4").val("");
        document.getElementById("selsr4").hidden = true;
      }
    },
  });
  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);
});

// Insurance Storage Select textbox
//  unit 1 Insurance Storage
$("#insurance_unit1").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btns 162021

  var insurance_unit1 = $("#insurance_unit1").val();
  var unit2 = $("#unit2_ins_hdn").val();
  var unit3 = $("#unit3_ins_hdn").val();
  var unit4 = $("#unit4_ins_hdn").val();
  if (insurance_unit1 >= 8000) {
    var ins_total = 14576;
    $("#unit1_ins").text("8M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  }else if(insurance_unit1 >= 5000) {
    var ins_total = 9110;
    $("#unit1_ins").text("5M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);

  }else if(insurance_unit1 >= 3500) {
    var ins_total = 6377;
    $("#unit1_ins").text("3.5M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);

  }else if(insurance_unit1 >= 2500) {
    var ins_total = 4555;
    $("#unit1_ins").text("2.5M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);

  } else if (insurance_unit1 >= 2000) {
    var ins_total = 3644;
    $("#unit1_ins").text("2M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 1500) {
    var ins_total = 2733;
    $("#unit1_ins").text("1.5M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 1000) {
    var ins_total = 1822;
    $("#unit1_ins").text("1M");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 750) {
    var ins_total = 1366;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 700) {
    var ins_total = 1275;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 650) {
    var ins_total = 1184;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 600) {
    var ins_total = 1092;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 550) {
    var ins_total = 1002;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 500) {
    var ins_total = 911;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 450) {
    var ins_total = 820;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 400) {
    var ins_total = 728;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 350) {
    var ins_total = 637;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 300) {
    var ins_total = 547;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 250) {
    var ins_total = 455;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 200) {
    var ins_total = 364;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 150) {
    var ins_total = 273;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 100) {
    var ins_total = 183;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else if (insurance_unit1 >= 50) {
    var ins_total = 91;
    $("#unit1_ins").text(insurance_unit1 + "K");
    $("#unit1_insurance_hdn").val(insurance_unit1);
    $("#unit1_ins_val").text(ins_total);
    $("#unit1_ins_hdn").val(ins_total);
  } else {
    var ins_total = 0;
    $("#unit1_ins").text("-");
    $("#unit1_insurance_hdn").val("");
    $("#unit1_ins_hdn").val("");
    $("#unit1_ins_val").text("");
  }
  var sum = (ins_total * 1 + unit2 * 1 + unit3 * 1 + unit4 * 1) / 1;
  document.getElementById("units_ins_val_total").innerHTML = numUSD.format(sum);
  $("#total_ins_final").val(sum);

  var peroid = $("#initial_period").val();
  var total = (peroid * 1) / 1;
  var ins1 = $("#unit1_ins_hdn").val();
  var ins2 = $("#unit2_ins_hdn").val();
  var ins3 = $("#unit3_ins_hdn").val();
  var ins4 = $("#unit4_ins_hdn").val();
  var total_ins = (ins1 * 1 + ins2 * 1 + ins3 * 1 + ins4 * 1) * total;
  document.getElementById("ins_total").innerHTML = numUSD.format(total_ins);
  $("#ins_total_hdn").val(total_ins);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

//  unit 2 Insurance Storage
$("#insurance_unit2").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btns 162021

  var insurance_unit2 = $("#insurance_unit2").val();
  if (insurance_unit2 >= 8000) {
    var ins_total = 14576.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("8M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
    
  }else if (insurance_unit2 >= 5000) {
    var ins_total = 9110;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("5M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

    }
    else if (insurance_unit2 >= 3500) {
    var ins_total = 6377.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("3.5M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

    }  else if (insurance_unit2 >= 2500) {
    var ins_total = 4555.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("2M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

  }  else if (insurance_unit2 >= 2000) {
    var ins_total = 3644.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("2M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 1000) {
    var ins_total = 1822.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("1M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 750) {
    var ins_total = 1366;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 700) {
    var ins_total = 1275;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 650) {
    var ins_total = 1184;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 600) {
    var ins_total = 1092;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 550) {
    var ins_total = 1002;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 500) {
    var ins_total = 911;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 450) {
    var ins_total = 820;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 400) {
    var ins_total = 728;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 350) {
    var ins_total = 637;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 300) {
    var ins_total = 547;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 250) {
    var ins_total = 455;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 200) {
    var ins_total = 364;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 150) {
    var ins_total = 273;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 100) {
    var ins_total = 183;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit2 >= 50) {
    var ins_total = 91;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit2 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else {
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("-");
    $("#unit2_insurance_hdn").val("");
    $("#unit2_ins_hdn").val("");
    $("#unit2_ins_val").text("");
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  }
  var peroid = $("#initial_period").val();
  var total = (peroid * 1) / 1;
  var ins1 = $("#unit1_ins_hdn").val();
  var ins2 = $("#unit2_ins_hdn").val();
  var ins3 = $("#unit3_ins_hdn").val();
  var ins4 = $("#unit4_ins_hdn").val();
  var total_ins = (ins1 * 1 + ins2 * 1 + ins3 * 1 + ins4 * 1) * total;
  document.getElementById("ins_total").innerHTML = numUSD.format(total_ins);
  $("#ins_total_hdn").val(total_ins);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});
//  unit 3 Insurance Storage
$("#insurance_unit3").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btns 162021

  var insurance_unit3 = $("#insurance_unit3").val();
  if (insurance_unit3 >= 8000) {
    var ins_total = 14576;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("8M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

    } else if (insurance_unit3 >= 5000) {
    var ins_total = 9110;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("5M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

  }else if (insurance_unit3 >= 2500) {
    var ins_total = 4555;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("2.5M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

  } else if (insurance_unit3 >= 2000) {
    var ins_total = 3644;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("2M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 1000) {
    var ins_total = 1822;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("1M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 750) {
    var ins_total = 1366;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit3 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit3);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 700) {
    var ins_total = 1275;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit3 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit3);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 650) {
    var ins_total = 1184;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit3 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit3);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 600) {
    var ins_total = 1092;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit3 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit3);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 550) {
    var ins_total = 1002;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text(insurance_unit3 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit3);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 500) {
    var ins_total = 911;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3);
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 450) {
    var ins_total = 820;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 400) {
    var ins_total = 728;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 350) {
    var ins_total = 637;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 300) {
    var ins_total = 547;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 250) {
    var ins_total = 455;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 200) {
    var ins_total = 364;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 150) {
    var ins_total = 273;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 100) {
    var ins_total = 183;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit3 >= 50) {
    var ins_total = 91;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + ins_total * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text(insurance_unit3 + "K");
    $("#unit3_insurance_hdn").val(insurance_unit3);
    $("#unit3_ins_val").text(ins_total);
    $("#unit3_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else {
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + unit4 * 1) / 1;
    $("#unit3_ins").text("-");
    $("#unit3_insurance_hdn").val("");
    $("#unit3_ins_hdn").val("");
    $("#unit3_ins_val").text("");
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  }
  var peroid = $("#initial_period").val();
  var total = (peroid * 1) / 1;
  var ins1 = $("#unit1_ins_hdn").val();
  var ins2 = $("#unit2_ins_hdn").val();
  var ins3 = $("#unit3_ins_hdn").val();
  var ins4 = $("#unit4_ins_hdn").val();
  var total_ins = (ins1 * 1 + ins2 * 1 + ins3 * 1 + ins4 * 1) * total;
  document.getElementById("ins_total").innerHTML = numUSD.format(total_ins);
  $("#ins_total_hdn").val(total_ins);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});
//  unit 4 Insurance Storage
$("#insurance_unit4").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btns 162021

  var insurance_unit4 = $("#insurance_unit4").val();
  if (insurance_unit4 >= 8000) {
    var ins_total = 14576.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("8M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

    }else if (insurance_unit4 >= 5000) {
    var ins_total = 9110.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("5M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

  } else if (insurance_unit4 >= 2500) {
    var ins_total = 4555.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("2.5M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);

  } else if (insurance_unit4 >= 2000) {
    var ins_total = 3644.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("2M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 1000) {
    var ins_total = 1822.0;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + ins_total * 1 + unit3 * 1 + unit4 * 1) / 1;
    $("#unit2_ins").text("1M");
    $("#unit2_insurance_hdn").val(insurance_unit2);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 750) {
    var ins_total = 1366;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit2_ins").text(insurance_unit4 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit4);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 700) {
    var ins_total = 1275;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit2_ins").text(insurance_unit4 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit4);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 650) {
    var ins_total = 1184;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit2_ins").text(insurance_unit4 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit4);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 600) {
    var ins_total = 1092;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit2_ins").text(insurance_unit4 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit4);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 550) {
    var ins_total = 1002;
    var unit1 = $("#unit1_ins_hdn").val();
    // var unit2 = $('#unit2_ins_hdn').val();
    var unit3 = $("#unit3_ins_hdn").val();
    var unit4 = $("#unit4_ins_hdn").val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit2_ins").text(insurance_unit4 + "K");
    $("#unit2_insurance_hdn").val(insurance_unit4);
    $("#unit2_ins_val").text(ins_total);
    $("#unit2_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 500) {
    var ins_total = 911;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 450) {
    var ins_total = 820;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 400) {
    var ins_total = 728;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 350) {
    var ins_total = 637;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 300) {
    var ins_total = 547;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 250) {
    var ins_total = 455;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 200) {
    var ins_total = 364;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 150) {
    var ins_total = 273;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 100) {
    var ins_total = 183;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else if (insurance_unit4 >= 50) {
    var ins_total = 91;
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1 + ins_total * 1) / 1;
    $("#unit4_ins").text(insurance_unit4 + "K");
    $("#unit4_insurance_hdn").val(insurance_unit4);
    $("#unit4_ins_val").text(ins_total);
    $("#unit4_ins_hdn").val(ins_total);
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  } else {
    var unit1 = $("#unit1_ins_hdn").val();
    var unit2 = $("#unit2_ins_hdn").val();
    var unit3 = $("#unit3_ins_hdn").val();
    // var unit4 = $('#unit4_ins_hdn').val();
    var sum = (unit1 * 1 + unit2 * 1 + unit3 * 1) / 1;
    $("#unit4_ins").text("-");
    $("#unit4_insurance_hdn").val("");
    $("#unit4_ins_hdn").val("");
    $("#unit4_ins_val").text("");
    $("#total_ins_final").val(sum);
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(sum);
  }
  var peroid = $("#initial_period").val();
  var total = (peroid * 1) / 1;
  var ins1 = $("#unit1_ins_hdn").val();
  var ins2 = $("#unit2_ins_hdn").val();
  var ins3 = $("#unit3_ins_hdn").val();
  var ins4 = $("#unit4_ins_hdn").val();
  var total_ins = (ins1 * 1 + ins2 * 1 + ins3 * 1 + ins4 * 1) * total;
  document.getElementById("ins_total").innerHTML = numUSD.format(total_ins);
  $("#ins_total_hdn").val(total_ins);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});
// Initial Peroid
$("#initial_period").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btns 162021
  var unit1 = $("#unit1").val();
  if (unit1 == "") {
    alert("Please Select a Unit");
    $("#unit1").focus();
  } else {
    $("#fee_discount").removeAttr("disabled");
    $("#fee_promo").removeAttr("disabled");
    $("#start").removeAttr("disabled");
  }

  var peroid = $("#initial_period").val();
  var total = (peroid * 1) / 1;

  if (total > 36) {
    alert("1-24 valid range");
    total = 24;
    $("#initial_period").val(total);
    $("#initial_period").focus();
  } else if (total < 1) {
    alert("1-24 valid range");
    total = 1;
    $("#initial_period").val(total);
    $("#initial_period").focus();
  }
  $("#initial_period_print").text(total);
  $("#initial_period_hdn").val(total);

  var ins1 = $("#unit1_ins_hdn").val();
  var ins2 = $("#unit2_ins_hdn").val();
  var ins3 = $("#unit3_ins_hdn").val();
  var ins4 = $("#unit4_ins_hdn").val();
  var total_ins = (ins1 * 1 + ins2 * 1 + ins3 * 1 + ins4 * 1) * total;
  document.getElementById("ins_total").innerHTML = numUSD.format(total_ins);
  $("#ins_total_hdn").val(total_ins);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);
  // disable unit

  // var unit1 = $('#unit1').val();
  // var unit2 = $('#unit2').val();
  // var unit3 = $('#unit3').val();
  // var unit4 = $('#unit4').val();
  // if(unit1==""){
  //   $('#unit1').attr('disabled','disabled');
  //   $('#insurance_unit1').attr('disabled','disabled');
  // }
  // if(unit2==""){
  //   $('#unit2').attr('disabled','disabled');
  //   $('#insurance_uni2').attr('disabled','disabled');
  // }
  // if(unit3==""){
  //   $('#unit3').attr('disabled','disabled');
  //   $('#insurance_unit3').attr('disabled','disabled');
  // }
  // if(unit4==""){
  //   $('#unit4').attr('disabled','disabled')
  //   $('#insurance_unit4').attr('disabled','disabled');
  // }
  // if(peroid!=""){
  //   $('#unit1').attr('disabled','disabled');
  //   $('#insurance_unit1').attr('disabled','disabled');
  //   $('#unit2').attr('disabled','disabled');
  //   $('#insurance_unit2').attr('disabled','disabled');
  //   $('#unit3').attr('disabled','disabled');
  //   $('#insurance_unit3').attr('disabled','disabled');
  //   $('#unit4').attr('disabled','disabled');
  //   $('#insurance_unit4').attr('disabled','disabled');
  // }

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});
// Storage Fee Discount
$("#fee_discount").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  //Disable btn 162021

  var fee = $("#fee_discount").val();
  var total_fee = $("#total_storage_fee_final").val();
  var unit1 = $("#unit1_price_hdn").val();
  var unit2 = $("#unit2_price_hdn").val();
  var unit3 = $("#unit3_price_hdn").val();
  var unit4 = $("#unit4_price_hdn").val();
  var initial_period = $("#initial_period").val();
  // if(fee!=""){
  $("#total_storage_discount_final").val(
    total_fee * 1 - (total_fee * 1 * (fee * 1)) / 1
  );
  $("#unit1_discount_hdn").val(unit1 * 1 - (unit1 * 1 * (fee * 1)) / 1);
  $("#unit2_discount_hdn").val(unit2 * 1 - (unit2 * 1 * (fee * 1)) / 1);
  $("#unit3_discount_hdn").val(unit3 * 1 - (unit3 * 1 * (fee * 1)) / 1);
  $("#unit4_discount_hdn").val(unit4 * 1 - (unit4 * 1 * (fee * 1)) / 1);
  document.getElementById("total_storage_fee").innerHTML = numUSD.format(
    total_fee * 1
  );
  $("#total_storage_fee_final").val(
    total_fee * 1 - (total_fee * 1 * (fee * 1)) / 1
  );
  $("#total_storage_fee_final_hdn").val(total_fee * 1);
  // $('#unit1_price').text(unit1*1);
  // $('#unit2_price').text(unit2*1);
  // $('#unit3_price').text(unit3*1);
  // $('#unit4_price').text(unit4*1);

  $("#unit1_price").text(unit1 * 1 - (unit1 * 1 * (fee * 1)) / 1);
  $("#unit2_price").text(unit2 * 1 - (unit2 * 1 * (fee * 1)) / 1);
  $("#unit3_price").text(unit3 * 1 - (unit3 * 1 * (fee * 1)) / 1);
  $("#unit4_price").text(unit4 * 1 - (unit4 * 1 * (fee * 1)) / 1);
  $("#total_storage_fee").text(
    unit1 * 1 -
      (unit1 * 1 * (fee * 1)) / 1 +
      unit2 * 1 -
      (unit2 * 1 * (fee * 1)) / 1 +
      unit3 * 1 -
      (unit3 * 1 * (fee * 1)) / 1 +
      unit4 * 1 -
      (unit4 * 1 * (fee * 1)) / 1
  );
  var num1 = ((total_fee * 1 * (fee * 1)) / 1) * initial_period * 1;
  $("#reduction_hdn1").val(num1);
  document.getElementById("reduction").innerHTML =
    "(" + numUSD.format(num1) + ")";

  // }else{
  //   $("#fee_promo").removeAttr("disabled");
  //   $('#initial_period').removeAttr('disabled');
  //   $('#total_storage_discount_final').val("");
  //   $('#unit1_discount_hdn').val("");
  //   $('#unit2_discount_hdn').val("");
  //   $('#unit3_discount_hdn').val("");
  //   $('#unit4_discount_hdn').val("");
  //   document.getElementById("total_storage_fee").innerHTML = numUSD.format(total_fee);
  //   $('#total_storage_fee_final').val(total_fee);
  //   $('#unit1_price').text(unit1);
  //   $('#unit2_price').text(unit2);
  //   $('#unit3_price').text(unit3);
  //   $('#unit4_price').text(unit4);
  // }

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});
// 1 Month Free Promo
$("#fee_promo").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  //Disable btn 162021

  var storage_fee = $("#total_storage_fee_final").val();
  var fee = $("#fee_promo").val();
  if (fee == "Yes") {
    var x = (document.getElementById("total_final").innerHTML =
      numUSD.format(storage_fee));
    $("#reduction").text("(" + x + ")");
    $("#reduction_hdn").val(storage_fee);
  } else {
    $("#reduction").text("-");
    $("#reduction_hdn").val(0);
  }

});

// Start Date
$("#start").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disabled btn 162021
  $("#anniversary").removeAttr("disabled");

  var date = $("#start").val();
  $("#start_date").val(date);
  var arr = date.toString().split("-");
  // alert(arr[0]); //2020
  // alert(arr[1]); //07 Month
  // alert(arr[2]); //22 Date
  var month = arr[1];
  var days = 0;
  var day = arr[2] - 1;
  if (month == "01") {
    days = 31;
  } else if (month == "02") {
    days = 28;
  } else if (month == "03") {
    days = 31;
  } else if (month == "04") {
    days = 30;
  } else if (month == "05") {
    days = 31;
  } else if (month == "06") {
    days = 30;
  } else if (month == "07") {
    days = 31;
  } else if (month == "08") {
    days = 31;
  } else if (month == "09") {
    days = 30;
  } else if (month == "10") {
    days = 31;
  } else if (month == "11") {
    days = 30;
  } else if (month == "12") {
    days = 31;
  }
  var partial = (days - day) / days;
  $("#partial_print").text(partial.toFixed(2));
  $("#partial_hdn").val(partial);

  // var insurance = $('#total_ins_final').val();
  // var current_partial = $('#partial_hdn').val();
  // var storage_period = $('#initial_period_hdn').val();

  // var com1 = storage_period * insurance;
  // var com2 = current_partial * insurance;
  // var computation = com1 + com2;

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#anniversary").change(function () {
  // Disabled btn 162021
  $("#adjustments_nonvat").removeAttr("disabled");
  $("#adjustments1").removeAttr("disabled");
  $("#adjustments2").removeAttr("disabled");
  $("#adjustments3").removeAttr("disabled");
  $("#adjustments4").removeAttr("disabled");
  $("#withholding_tax").removeAttr("disabled");
  $("#tax_exempt").removeAttr("disabled");
  $("#renewal").removeAttr("disabled");

  var anniv = $("#anniversary").val();
  if (anniv == "Anniversary") {
    $("#partial_hdn").val(0);
    $("#partial_print").text("0.00");
  } else {
  }
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();

  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;

  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);

  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#adjustments_nonvat").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  var nonvat = $("#adjustments_nonvat").val();
  document.getElementById("adjustments_nonvat_print").innerHTML =
    numUSD.format(nonvat);
  $("#adjustments_nonvat_print_hdn").val(nonvat);
  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#adjustments1").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });

  var adj1 = $("#adjustments1").val();
  document.getElementById("adjustment1").innerHTML = numUSD.format(adj1);
  $("#adjustment1_hdn").val(adj1);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  $("#deposit_notax_hdn").val(total_pricenet);
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#adjustments2").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });

  var adj2 = $("#adjustments2").val();
  document.getElementById("adjustment2").innerHTML = numUSD.format(adj2);
  $("#adjustment2_hdn").val(adj2);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#adjustments3").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });

  var adj3 = $("#adjustments3").val();
  document.getElementById("adjustment3").innerHTML = numUSD.format(adj3);
  $("#adjustment3_hdn").val(adj3);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});
$("#adjustments4").on("change", function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });

  var adj4 = $("#adjustments4").val();
  document.getElementById("adjustment4").innerHTML = numUSD.format(adj4);
  $("#adjustment4_hdn").val(adj4);

  // Final total value
  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var total_pricenet =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  var total_value =
    total_pricenet * 1 +
    admin_fee * 1 +
    computation * 1 +
    adj_nonvat * 1 +
    adjustments1 * 1 +
    adjustments2 * 1 +
    adjustments3 * 1 +
    adjustments4 * 1 +
    total_storage_fee * 1 -
    reduction * 1;
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

// dito
$("#withholding_tax").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btn 162021

  var withholding_tax = $("#withholding_tax").val();
  var withhold = 0.02;
  var vat = 1.12;
  if (withholding_tax == "Yes") {
    //IF YES Final total value IF YES
    var insurance = $("#total_ins_final").val();
    var current_partial = $("#partial_hdn").val();
    var storage_period = $("#initial_period_hdn").val();
    var storage_fee = $("#total_storage_fee_final").val();
    var com1 = storage_period * insurance;
    var com2 = current_partial * insurance;
    var computation = com1 + com2;
    document.getElementById("ins_total").innerHTML = numUSD.format(computation);
    $("#ins_total_hdn").val(computation);
    var com3 = storage_period * 1 + current_partial * 1;
    var total_storage_fee = storage_fee * com3;
    document.getElementById("final_storage_fee").innerHTML =
      numUSD.format(total_storage_fee);
    $("#final_storage_fee_hdn").val(total_storage_fee);
    var unit1_pricenet = $("#unit1_pricenet_hdn").val();
    var unit2_pricenet = $("#unit2_pricenet_hdn").val();
    var unit3_pricenet = $("#unit3_pricenet_hdn").val();
    var unit4_pricenet = $("#unit4_pricenet_hdn").val();
    var reduction = $("#reduction_hdn").val();
    var total_pricenet =
      (unit1_pricenet * 1 +
        unit2_pricenet * 1 +
        unit3_pricenet * 1 +
        unit4_pricenet * 1) /
      1;
    var admin_fee = $("#admin_fee_hdn").val();
    var adj_nonvat = $("#adjustments_nonvat").val();
    var adjustments1 = $("#adjustments1").val();
    var adjustments2 = $("#adjustments2").val();
    var adjustments3 = $("#adjustments3").val();
    var adjustments4 = $("#adjustments4").val();
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1 -
      reduction * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
    document.getElementById("withholding_tax_print").innerHTML =
      numUSD.format(final_withhold);
    $("#withholding_tax_hdn").val(final_withhold);
    var total_value =
      total_pricenet * 1 +
      admin_fee * 1 +
      computation * 1 +
      adj_nonvat * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1 +
      total_storage_fee * 1 -
      final_withhold * 1 -
      reduction * 1;
    $("#total_final_hdn").val(total_value);
    document.getElementById("total_final").innerHTML =
      numUSD.format(total_value);

    // $('#reduction').text('');
    // $('#reduction_hdn').val('0');
    // $('#reduction_hdn1').val('0');
  } else {
    // Final total value
    var insurance = $("#total_ins_final").val();
    var current_partial = $("#partial_hdn").val();
    var storage_period = $("#initial_period_hdn").val();
    var storage_fee = $("#total_storage_fee_final").val();
    var com1 = storage_period * insurance;
    var com2 = current_partial * insurance;
    var computation = com1 + com2;
    document.getElementById("ins_total").innerHTML = numUSD.format(computation);
    $("#ins_total_hdn").val(computation);
    var com3 = storage_period * 1 + current_partial * 1;
    var total_storage_fee = storage_fee * com3;
    document.getElementById("final_storage_fee").innerHTML =
      numUSD.format(total_storage_fee);
    $("#final_storage_fee_hdn").val(total_storage_fee);
    var unit1_pricenet = $("#unit1_pricenet_hdn").val();
    var unit2_pricenet = $("#unit2_pricenet_hdn").val();
    var unit3_pricenet = $("#unit3_pricenet_hdn").val();
    var unit4_pricenet = $("#unit4_pricenet_hdn").val();
    var total_pricenet =
      (unit1_pricenet * 1 +
        unit2_pricenet * 1 +
        unit3_pricenet * 1 +
        unit4_pricenet * 1) /
      1;
    var admin_fee = $("#admin_fee_hdn").val();
    var adj_nonvat = $("#adjustments_nonvat").val();
    var adjustments1 = $("#adjustments1").val();
    var adjustments2 = $("#adjustments2").val();
    var adjustments3 = $("#adjustments3").val();
    var adjustments4 = $("#adjustments4").val();
    var reduction = $("#reduction_hdn").val();
    var total_value =
      total_pricenet * 1 +
      admin_fee * 1 +
      computation * 1 +
      adj_nonvat * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1 +
      total_storage_fee * 1 -
      reduction * 1;
    $("#total_final_hdn").val(total_value);
    document.getElementById("total_final").innerHTML =
      numUSD.format(total_value);
    document.getElementById("withholding_tax_print").innerHTML =
      numUSD.format(0);
    $("withholding_tax_hdn").val(0);
  }

  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax =
    (unit1_pricenet * 1 +
      unit2_pricenet * 1 +
      unit3_pricenet * 1 +
      unit4_pricenet * 1) /
    1;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#tax_exempt").change(function () {
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btn 162021

  var tax = $("#tax_exempt").val();
  if (tax == "Yes") {
    // Price
    var tax_unit1_hdn = $("#unit1_price_hdn").val() / 1.12;
    var tax_unit2_hdn = $("#unit2_price_hdn").val() / 1.12;
    var tax_unit3_hdn = $("#unit3_price_hdn").val() / 1.12;
    var tax_unit4_hdn = $("#unit4_price_hdn").val() / 1.12;
    var tax_total_hdn = $("#total_storage_fee_final").val() / 1.12;
    $("#unit1_price_hdn").val(tax_unit1_hdn);
    $("#unit2_price_hdn").val(tax_unit2_hdn);
    $("#unit3_price_hdn").val(tax_unit3_hdn);
    $("#unit4_price_hdn").val(tax_unit4_hdn);
    $("#total_storage_fee_final").val(tax_total_hdn);
    $("#late_fee").val(tax_total_hdn * 0.1);
    $("#unit1_price").text(tax_unit1_hdn.toFixed(2));
    $("#unit2_price").text(tax_unit2_hdn.toFixed(2));
    $("#unit3_price").text(tax_unit3_hdn.toFixed(2));
    $("#unit4_price").text(tax_unit4_hdn.toFixed(2));
    // $('#total_storage_fee').text(tax_total_hdn);
    document.getElementById("total_storage_fee").innerHTML =
      numUSD.format(tax_total_hdn);

    // Discount
    var tax_discount1 = $("#unit1_discount_hdn").val() / 1.12;
    var tax_discount2 = $("#unit2_discount_hdn").val() / 1.12;
    var tax_discount3 = $("#unit3_discount_hdn").val() / 1.12;
    var tax_discount4 = $("#unit4_discount_hdn").val() / 1.12;
    var tax_total_discount = $("#total_storage_discount_final").val() / 1.12;

    $("#unit1_discount_hdn").val(tax_discount1);
    $("#unit2_discount_hdn").val(tax_discount2);
    $("#unit3_discount_hdn").val(tax_discount3);
    $("#unit4_discount_hdn").val(tax_discount4);
    $("#total_storage_discount_final").val(tax_total_discount);

    // Insurance
    var unit1_ins = $("#unit1_ins_hdn").val() / 1.12;
    var unit2_ins = $("#unit2_ins_hdn").val() / 1.12;
    var unit3_ins = $("#unit3_ins_hdn").val() / 1.12;
    var unit4_ins = $("#unit4_ins_hdn").val() / 1.12;
    var total_ins = $("#total_ins_final").val() / 1.12;

    $("#unit1_ins_hdn").val(unit1_ins);
    $("#unit2_ins_hdn").val(unit2_ins);
    $("#unit3_ins_hdn").val(unit3_ins);
    $("#unit4_ins_hdn").val(unit4_ins);
    $("#total_ins_final").val(total_ins);

    $("#unit1_ins_val").text(unit1_ins.toFixed(2));
    $("#unit2_ins_val").text(unit2_ins.toFixed(2));
    $("#unit3_ins_val").text(unit3_ins.toFixed(2));
    $("#unit4_ins_val").text(unit4_ins.toFixed(2));
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(total_ins);

    // admin fee
    var adminfee = $("#admin_fee_hdn").val() / 1.12;

    $("#admin_fee_hdn").val(adminfee.toFixed(2));
    document.getElementById("admin_fee").innerHTML = numUSD.format(adminfee);
    // Final total value
    var withholding_tax = $("#withholding_tax").val();
    var insurance = $("#total_ins_final").val();
    var current_partial = $("#partial_hdn").val();
    var storage_period = $("#initial_period_hdn").val();
    var storage_fee = $("#total_storage_fee_final").val();
    var com1 = storage_period * insurance;
    var com2 = current_partial * insurance;
    var computation = com1 + com2;
    document.getElementById("ins_total").innerHTML = numUSD.format(computation);
    $("#ins_total_hdn").val(computation);
    var com3 = storage_period * 1 + current_partial * 1;
    var total_storage_fee = storage_fee * com3;
    document.getElementById("final_storage_fee").innerHTML =
      numUSD.format(total_storage_fee);
    $("#final_storage_fee_hdn").val(total_storage_fee);
    var unit1_pricenet = $("#unit1_pricenet_hdn").val();
    var unit2_pricenet = $("#unit2_pricenet_hdn").val();
    var unit3_pricenet = $("#unit3_pricenet_hdn").val();
    var unit4_pricenet = $("#unit4_pricenet_hdn").val();
    var total_pricenet =
      (unit1_pricenet * 1 +
        unit2_pricenet * 1 +
        unit3_pricenet * 1 +
        unit4_pricenet * 1) /
      1;
    var admin_fee = $("#admin_fee_hdn").val();
    var adj_nonvat = $("#adjustments_nonvat").val();
    var adjustments1 = $("#adjustments1").val();
    var adjustments2 = $("#adjustments2").val();
    var adjustments3 = $("#adjustments3").val();
    var adjustments4 = $("#adjustments4").val();
    var reduction = $("#reduction_hdn").val() / 1.12;
    if (withholding_tax == "Yes") {
      var withhold = 0.02;
      var vat = 1;
      var tot_withhold =
        computation * 1 +
        total_storage_fee * 1 +
        admin_fee * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1 -
        reduction * 1;
      var f_withhold = tot_withhold * withhold;
      var final_withhold = f_withhold / vat;
      document.getElementById("withholding_tax_print").innerHTML =
        numUSD.format(final_withhold);
      $("#withholding_tax_hdn").val(final_withhold);
      var total_value =
        total_pricenet * 1 +
        admin_fee * 1 +
        computation * 1 +
        adj_nonvat * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1 +
        total_storage_fee * 1 -
        final_withhold * 1 -
        reduction * 1;
    } else {
      var total_value =
        total_pricenet * 1 +
        admin_fee * 1 +
        computation * 1 +
        adj_nonvat * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1 +
        total_storage_fee * 1 -
        reduction * 1;
    }
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
    $("#total_final_hdn").val(total_value);
    document.getElementById("total_final").innerHTML =
      numUSD.format(total_value);

    document.getElementById("reduction").innerHTML = numUSD.format(reduction);
    $("#reduction_hdn").val(reduction);
    $("#reduction_hdn1").val(reduction);
  } else {
    var tax_unit1_hdn = $("#unit1_price_hdn").val() * 1.12;
    var tax_unit2_hdn = $("#unit2_price_hdn").val() * 1.12;
    var tax_unit3_hdn = $("#unit3_price_hdn").val() * 1.12;
    var tax_unit4_hdn = $("#unit4_price_hdn").val() * 1.12;
    var tax_total_hdn = $("#total_storage_fee_final").val() * 1.12;
    $("#unit1_price_hdn").val(tax_unit1_hdn);
    $("#unit2_price_hdn").val(tax_unit2_hdn);
    $("#unit3_price_hdn").val(tax_unit3_hdn);
    $("#unit4_price_hdn").val(tax_unit4_hdn);
    $("#total_storage_fee_final").val(tax_total_hdn);
    $("#late_fee").val(tax_total_hdn * 0.1);
    $("#unit1_price").text(tax_unit1_hdn.toFixed(2));
    $("#unit2_price").text(tax_unit2_hdn.toFixed(2));
    $("#unit3_price").text(tax_unit3_hdn.toFixed(2));
    $("#unit4_price").text(tax_unit4_hdn.toFixed(2));
    // $('#total_storage_fee').text(tax_total_hdn);
    document.getElementById("total_storage_fee").innerHTML =
      numUSD.format(tax_total_hdn);

    // Discount
    var tax_discount1 = $("#unit1_discount_hdn").val() * 1.12;
    var tax_discount2 = $("#unit2_discount_hdn").val() * 1.12;
    var tax_discount3 = $("#unit3_discount_hdn").val() * 1.12;
    var tax_discount4 = $("#unit4_discount_hdn").val() * 1.12;
    var tax_total_discount = $("#total_storage_discount_final").val() * 1.12;

    $("#unit1_discount_hdn").val(tax_discount1);
    $("#unit2_discount_hdn").val(tax_discount2);
    $("#unit3_discount_hdn").val(tax_discount3);
    $("#unit4_discount_hdn").val(tax_discount4);
    $("#total_storage_discount_final").val(tax_total_discount);

    // Insurance
    var unit1_ins = $("#unit1_ins_hdn").val() * 1.12;
    var unit2_ins = $("#unit2_ins_hdn").val() * 1.12;
    var unit3_ins = $("#unit3_ins_hdn").val() * 1.12;
    var unit4_ins = $("#unit4_ins_hdn").val() * 1.12;
    var total_ins = $("#total_ins_final").val() * 1.12;

    $("#unit1_ins_hdn").val(unit1_ins);
    $("#unit2_ins_hdn").val(unit2_ins);
    $("#unit3_ins_hdn").val(unit3_ins);
    $("#unit4_ins_hdn").val(unit4_ins);
    $("#total_ins_final").val(total_ins);

    $("#unit1_ins_val").text(unit1_ins.toFixed(2));
    $("#unit2_ins_val").text(unit2_ins.toFixed(2));
    $("#unit3_ins_val").text(unit3_ins.toFixed(2));
    $("#unit4_ins_val").text(unit4_ins.toFixed(2));
    document.getElementById("units_ins_val_total").innerHTML =
      numUSD.format(total_ins);

    // admin fee
    var adminfee = $("#admin_fee_hdn").val() * 1.12;

    $("#admin_fee_hdn").val(adminfee.toFixed(2));
    document.getElementById("admin_fee").innerHTML = numUSD.format(adminfee);

    // Final total value
    var withholding_tax = $("#withholding_tax").val();
    var insurance = $("#total_ins_final").val();
    var current_partial = $("#partial_hdn").val();
    var storage_period = $("#initial_period_hdn").val();
    var storage_fee = $("#total_storage_fee_final").val();
    var com1 = storage_period * insurance;
    var com2 = current_partial * insurance;
    var computation = com1 + com2;
    document.getElementById("ins_total").innerHTML = numUSD.format(computation);
    $("#ins_total_hdn").val(computation);
    var com3 = storage_period * 1 + current_partial * 1;
    var total_storage_fee = storage_fee * com3;
    document.getElementById("final_storage_fee").innerHTML =
      numUSD.format(total_storage_fee);
    $("#final_storage_fee_hdn").val(total_storage_fee);
    var unit1_pricenet = $("#unit1_pricenet_hdn").val();
    var unit2_pricenet = $("#unit2_pricenet_hdn").val();
    var unit3_pricenet = $("#unit3_pricenet_hdn").val();
    var unit4_pricenet = $("#unit4_pricenet_hdn").val();
    var total_pricenet =
      (unit1_pricenet * 1 +
        unit2_pricenet * 1 +
        unit3_pricenet * 1 +
        unit4_pricenet * 1) /
      1;
    var admin_fee = $("#admin_fee_hdn").val();
    var adj_nonvat = $("#adjustments_nonvat").val();
    var adjustments1 = $("#adjustments1").val();
    var adjustments2 = $("#adjustments2").val();
    var adjustments3 = $("#adjustments3").val();
    var adjustments4 = $("#adjustments4").val();
    var reduction = $("#reduction_hdn").val();
    if (withholding_tax == "Yes") {
      var withhold = 0.02;
      var vat = 1.12;
      var tot_withhold =
        computation * 1 +
        total_storage_fee * 1 +
        admin_fee * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1;
      var f_withhold = tot_withhold * withhold;
      var final_withhold = f_withhold / vat;
      document.getElementById("withholding_tax_print").innerHTML =
        numUSD.format(final_withhold);
      $("#withholding_tax_hdn").val(final_withhold);
      var total_value =
        total_pricenet * 1 +
        admin_fee * 1 +
        computation * 1 +
        adj_nonvat * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1 +
        total_storage_fee * 1 -
        final_withhold * 1 -
        reduction * 1;
    } else {
      var total_value =
        total_pricenet * 1 +
        admin_fee * 1 +
        computation * 1 +
        adj_nonvat * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1 +
        total_storage_fee * 1 -
        reduction * 1;
    }

    $("#total_final_hdn").val(total_value);
    document.getElementById("total_final").innerHTML =
      numUSD.format(total_value);

    // memo vat
    // tax exempt
    var tax_exempt = $("#tax_exempt").val;
    // total
    var total = $("#total_final_hdn").val();
    // non vat
    var non_vat = $("#adjustments_nonvat").val();
    // Deposit (no tax)
    var unit1_pricenet = $("#unit1_pricenet_hdn").val();
    var unit2_pricenet = $("#unit2_pricenet_hdn").val();
    var unit3_pricenet = $("#unit3_pricenet_hdn").val();
    var unit4_pricenet = $("#unit4_pricenet_hdn").val();
    var deposit_notax =
      (unit1_pricenet * 1 +
        unit2_pricenet * 1 +
        unit3_pricenet * 1 +
        unit4_pricenet * 1) /
      1;
    // witholding tax
    var withholding_tax = $("#withholding_tax").val();
    var tax_exempt = $("#tax_exempt").val();
    if (withholding_tax == "Yes") {
      var withhold = 0.02;
      if (tax_exempt == "Yes") {
        var vat = 1;
      } else {
        var vat = 1.12;
      }
      var tot_withhold =
        computation * 1 +
        total_storage_fee * 1 +
        admin_fee * 1 +
        adjustments1 * 1 +
        adjustments2 * 1 +
        adjustments3 * 1 +
        adjustments4 * 1;
      var f_withhold = tot_withhold * withhold;
      var final_withhold = f_withhold / vat;
    } else {
      var final_withhold = 0;
    }
    // computation
    if (tax_exempt == "Yes") {
      var x = 0;
    } else {
      var x = 1;
    }
    var com1 =
      ((total * 1 + final_withhold * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
});

$("#renewal").change(function () {
  var renewal = $("#renewal").val();

  // if(renewal=="Yes"){
  //    $('#unit1_price_hdn').attr('disabled','disabled');
  //    $('#unit2_price_hdn').attr('disabled','disabled');
  //    $('#unit3_price_hdn').attr('disabled','disabled');
  //    $('#unit4_price_hdn').attr('disabled','disabled');
  // }else{
  //    $('#renewal').attr('disabled','disabled');
  // }
  var numUSD = new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "PHP",
  });
  // Disable btn 162021

  $("#deposit_notax").text("-");
  $("#admin_fee").text("-");
  $("#admin_fee_hdn").val("");
  $("#deposit_notax_hdn").val("");

  // Final total value
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();

  var insurance = $("#total_ins_final").val();
  var current_partial = $("#partial_hdn").val();
  var storage_period = $("#initial_period_hdn").val();
  var storage_fee = $("#total_storage_fee_final").val();
  var com1 = storage_period * insurance;
  var com2 = current_partial * insurance;
  var computation = com1 + com2;
  document.getElementById("ins_total").innerHTML = numUSD.format(computation);
  $("#ins_total_hdn").val(computation);
  var com3 = storage_period * 1 + current_partial * 1;
  var total_storage_fee = storage_fee * com3;
  document.getElementById("final_storage_fee").innerHTML =
    numUSD.format(total_storage_fee);
  $("#final_storage_fee_hdn").val(total_storage_fee);
  // var unit1_pricenet = $('#unit1_pricenet_hdn').val();
  // var unit2_pricenet = $('#unit2_pricenet_hdn').val();
  // var unit3_pricenet = $('#unit3_pricenet_hdn').val();
  // var unit4_pricenet = $('#unit4_pricenet_hdn').val();
  // var total_pricenet = (unit1_pricenet*1 + unit2_pricenet*1 + unit3_pricenet*1 + unit4_pricenet*1)/1;
  var admin_fee = $("#admin_fee_hdn").val();
  var adj_nonvat = $("#adjustments_nonvat").val();
  var adjustments1 = $("#adjustments1").val();
  var adjustments2 = $("#adjustments2").val();
  var adjustments3 = $("#adjustments3").val();
  var adjustments4 = $("#adjustments4").val();
  var reduction = $("#reduction_hdn").val();
  // var total_value = (admin_fee*1 + computation*1 + adj_nonvat*1 + adjustments1*1 + adjustments2*1 + adjustments3*1 + total_storage_fee*1) - reduction*1;
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1 -
      reduction * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
    document.getElementById("withholding_tax_print").innerHTML =
      numUSD.format(final_withhold);
    $("#withholding_tax_hdn").val(final_withhold);
    var total_value =
      admin_fee * 1 +
      computation * 1 +
      adj_nonvat * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1 +
      total_storage_fee * 1 -
      final_withhold * 1 -
      reduction * 1;
  } else {
    var total_value =
      admin_fee * 1 +
      computation * 1 +
      adj_nonvat * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1 +
      total_storage_fee * 1 -
      reduction * 1;
  }
  $("#total_final_hdn").val(total_value);
  document.getElementById("total_final").innerHTML = numUSD.format(total_value);
  document.getElementById("reduction").innerHTML = numUSD.format(reduction);
  $("#reduction_hdn").val(reduction);
  $("#reduction_hdn1").val(reduction);
  // memo vat
  // tax exempt
  var tax_exempt = $("#tax_exempt").val;
  // total
  var total = $("#total_final_hdn").val();
  // non vat
  var non_vat = $("#adjustments_nonvat").val();
  // Deposit (no tax)
  var unit1_pricenet = $("#unit1_pricenet_hdn").val();
  var unit2_pricenet = $("#unit2_pricenet_hdn").val();
  var unit3_pricenet = $("#unit3_pricenet_hdn").val();
  var unit4_pricenet = $("#unit4_pricenet_hdn").val();
  var deposit_notax = 0;
  // witholding tax
  var withholding_tax = $("#withholding_tax").val();
  var tax_exempt = $("#tax_exempt").val();
  if (withholding_tax == "Yes") {
    var withhold = 0.02;
    if (tax_exempt == "Yes") {
      var vat = 1;
    } else {
      var vat = 1.12;
    }
    var tot_withhold =
      computation * 1 +
      total_storage_fee * 1 +
      admin_fee * 1 +
      adjustments1 * 1 +
      adjustments2 * 1 +
      adjustments3 * 1 +
      adjustments4 * 1;
    var f_withhold = tot_withhold * withhold;
    var final_withhold = f_withhold / vat;
  } else {
    var final_withhold = 0;
  }
  // computation
  if (tax_exempt == "Yes") {
    $("#memo_vat").text("-");
    $("#memo_vat_hdn").val(0);
  } else {
    var withholding_tax_hdn = $("#withholding_tax_hdn").val();
    var x = 1;
    var com1 =
      ((total * 1 + withholding_tax_hdn * 1 - non_vat * 1 - deposit_notax * 1) /
        1.12) *
      0.12 *
      (x * 1);
    document.getElementById("memo_vat").innerHTML = numUSD.format(com1);
    $("#memo_vat_hdn").val(com1);
  }
  $("#late_fee").val(storage_fee * 0.1);
});

// Manual unit input
$("#unit1_price_hdn").keyup(function () {
  var unit1_price_hdn = $("#unit1_price_hdn").val();
  var unit2_price_hdn = $("#unit2_price_hdn").val();
  var unit3_price_hdn = $("#unit3_price_hdn").val();
  var unit4_price_hdn = $("#unit4_price_hdn").val();
  var total =
    unit1_price_hdn * 1 +
    unit2_price_hdn * 1 +
    unit3_price_hdn * 1 +
    unit4_price_hdn * 1;
  // alert(total);
  $("#total_storage_fee_final").val(total);
  document.getElementById("total_storage_fee").innerHTML = numUSD.format(total);
});

$("#unit2_price_hdn").keyup(function () {
  var unit1_price_hdn = $("#unit1_price_hdn").val();
  var unit2_price_hdn = $("#unit2_price_hdn").val();
  var unit3_price_hdn = $("#unit3_price_hdn").val();
  var unit4_price_hdn = $("#unit4_price_hdn").val();
  var total =
    unit1_price_hdn * 1 +
    unit2_price_hdn * 1 +
    unit3_price_hdn * 1 +
    unit4_price_hdn * 1;
  // alert(total);
  $("#total_storage_fee_final").val(total);
  document.getElementById("total_storage_fee").innerHTML = numUSD.format(total);
});

$("#unit3_price_hdn").keyup(function () {
  var unit1_price_hdn = $("#unit1_price_hdn").val();
  var unit2_price_hdn = $("#unit2_price_hdn").val();
  var unit3_price_hdn = $("#unit3_price_hdn").val();
  var unit4_price_hdn = $("#unit4_price_hdn").val();
  var total =
    unit1_price_hdn * 1 +
    unit2_price_hdn * 1 +
    unit3_price_hdn * 1 +
    unit4_price_hdn * 1;
  // alert(total);
  $("#total_storage_fee_final").val(total);
  document.getElementById("total_storage_fee").innerHTML = numUSD.format(total);
});

$("#unit4_price_hdn").keyup(function () {
  var unit1_price_hdn = $("#unit1_price_hdn").val();
  var unit2_price_hdn = $("#unit2_price_hdn").val();
  var unit3_price_hdn = $("#unit3_price_hdn").val();
  var unit4_price_hdn = $("#unit4_price_hdn").val();
  var total =
    unit1_price_hdn * 1 +
    unit2_price_hdn * 1 +
    unit3_price_hdn * 1 +
    unit4_price_hdn * 1;
  // alert(total);
  $("#total_storage_fee_final").val(total);
  document.getElementById("total_storage_fee").innerHTML = numUSD.format(total);
});

