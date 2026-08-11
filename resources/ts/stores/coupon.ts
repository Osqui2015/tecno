import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from '@/bootstrap';

export const useCouponStore = defineStore('coupon', () => {
    const code = ref('');
    const discount = ref(0);
    const finalSubtotal = ref(0);
    const appliedCoupon = ref<{ code: string; type: string; value: number } | null>(null);
    const error = ref<string | null>(null);
    const validating = ref(false);

    async function validate(subtotal: number) {
        if (!code.value.trim()) return;
        validating.value = true;
        error.value = null;
        try {
            const { data } = await axios.post('/coupons/validate', {
                code: code.value.trim(),
                subtotal,
            });
            appliedCoupon.value = data.coupon;
            discount.value = Number(data.discount);
            finalSubtotal.value = Number(data.final_subtotal);
            return true;
        } catch (e: any) {
            error.value = e.response?.data?.errors?.code?.[0]
                || e.response?.data?.message
                || 'Cupón inválido';
            appliedCoupon.value = null;
            discount.value = 0;
            return false;
        } finally {
            validating.value = false;
        }
    }

    function remove() {
        appliedCoupon.value = null;
        discount.value = 0;
        code.value = '';
        error.value = null;
    }

    return {
        code,
        discount,
        finalSubtotal,
        appliedCoupon,
        error,
        validating,
        validate,
        remove,
    };
});
