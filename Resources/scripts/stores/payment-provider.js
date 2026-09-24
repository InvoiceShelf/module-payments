import { useNotificationStore } from '@/scripts/stores/notification'
// InvoiceShelf 2.3.0 stopped exposing window.axios; its HTTP client (auth
// token, company header, credentials) is bundled from the host instead.
import http from '@/scripts/http'
const { defineStore } = window.pinia
import { handleError } from '../helpers/error-handling'

// The id is the first argument: Pinia 3, which InvoiceShelf ships from 2.3.0,
// dropped the defineStore({ id, ... }) form.
export const usePaymentProviderStore = defineStore('payment-provider', {
  state: () => ({
    paymentDrivers: [],
    paymentProviders: [],
    activeProviders: [],
    selectedProvider: null,
    currentInvoice: null,

    currentPaymentProvider: {
      id: null,
      name: '',
      driver: '',
      active: false,
      use_test_env: false,
      settings: {
        key: '',
        secret: '',
      },
    },
  }),

  getters: {
    isEdit: (state) => (state.currentPaymentProvider.id ? true : false),
  },

  actions: {
    generatePayment(company) {
      return new Promise((resolve, reject) => {
        if (!this.selectedProvider) {
          return
        }

        let data = {
          payment_method_id: this.selectedProvider.id,
        }

        http
          .post(
            `/api/m/payments/${company}/generate-payment/${this.currentInvoice.id}`,
            data
          )
          .then((response) => {
            resolve(response)
          })
          .catch((err) => {
            reject(err)
          })
      })
    },

    confirmTransaction(uniqueHash, data = {}) {
      if (!this.selectedProvider) {
        return
      }

      data.payment_method_id = this.selectedProvider.id

      return new Promise((resolve, reject) => {

        http
          .post(
            `/api/m/payments/${data.company_id}/confirm-transaction/${uniqueHash}`,
            data
          )
          .then((response) => {
            resolve(response)
          })
          .catch((err) => {
            reject(err)
          })
      })
    },

    fetchPaymentProviders() {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/m/payments/payment-providers`)
          .then((response) => {
            this.paymentProviders = response.data.data
            resolve(response)
          })
          .catch((err) => {
            reject(err)
          })
      })
    },

    fetchPaymentProvider(id) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/m/payments/payment-providers/${id}`)
          .then((response) => {
            Object.assign(this.currentPaymentProvider, response.data.data)
            resolve(response)
          })
          .catch((err) => {
            reject(err)
          })
      })
    },

    addPaymentProvider(data) {
      const { global } = window.i18n
      const notificationStore = useNotificationStore(true)
      return new Promise((resolve, reject) => {
        http
          .post('/api/m/payments/payment-providers', data)
          .then((response) => {
            notificationStore.showNotification({
              type: 'success',
              message: global.t('payment_providers.provider_add_message'),
            })
            resolve(response)
          })
          .catch((err) => {
            notificationStore.showNotification({
              type: 'error',
              message: global.t('payment_providers.invalid_credentials'),
            })
            reject(err)
          })
      })
    },

    updatePaymentProvider(data) {
      const { global } = window.i18n

      const notificationStore = useNotificationStore(true)
      return new Promise((resolve, reject) => {
        http
          .put(`/api/m/payments/payment-providers/${data.id}`, data)
          .then((response) => {
            notificationStore.showNotification({
              type: 'success',
              message: global.t('payment_providers.provider_updated_message'),
            })
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    deletePaymentProvider(id) {
      const { global } = window.i18n

      const notificationStore = useNotificationStore(true)
      return new Promise((resolve, reject) => {
        http
          .delete(`/api/m/payments/payment-providers/${id}`)
          .then((response) => {
            let index = this.paymentProviders.findIndex(
              (driver) => driver.id === id
            )
            this.paymentProviders.splice(index, 1)
            if (response.data.success) {
              notificationStore.showNotification({
                type: 'success',
                message: global.t('payment_providers.provider_delete_message'),
              })
            } else {
              notificationStore.showNotification({
                type: 'error',
                message: 'payment_providers.error',
              })
            }
            resolve(response)
          })
          .catch((err) => {
            if (err.response.data.error === "payments_attached") {
              notificationStore.showNotification({
                type: 'error',
                message: global.t('payment_providers.already_in_use'),
              })
            }
            handleError(err)
            reject(err)
          })
      })
    },

    fetchPaymentDrivers() {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/m/payments/payment-drivers`)
          .then((response) => {
            this.paymentDrivers = response.data.payment_drivers
            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },

    fetchActiveProviders(company) {
      return new Promise((resolve, reject) => {
        http
          .get(`/api/m/payments/${company}/active-payment-providers`)
          .then((response) => {
            this.activeProviders = response.data.data

            if (this.activeProviders.length >= 1) {
              this.selectedProvider = this.activeProviders[0]
            }

            resolve(response)
          })
          .catch((err) => {
            handleError(err)
            reject(err)
          })
      })
    },
  },
})
