<template>
  <div class="default-main">
    <el-row v-loading="state.loading" :gutter="20">
      <el-col :sm="12" :xs="24" class="xs-mb-20">
        <el-card class="box-card">
          <el-form :label-width="200" :model="state.formData" label-position="top" @keyup.enter="submitOssConfig()">
            <el-divider content-position="left">{{ t('routine.oss.basic_setting') }}</el-divider>
            <FormItem
                v-model.number="state.formData.storage_driver"
                :data="{
                    content: {
                        local: t('routine.oss.local'),
                        aliyun: t('routine.oss.aliyun'),
                        cos: t('routine.oss.cos'),
                        qiniu: t('routine.oss.qiniu'),
                    },
                    childrenAttr: { border: true }
                }"
                :label="t('routine.oss.storage_driver')"
                type="radio"
            />
            <div v-if="state.formData.storage_driver === 'aliyun'">
              <el-divider content-position="left">{{ t('routine.oss.aliyun_config') }}</el-divider>
              <FormItem v-model="state.formData.aliyun.access_key_id" :label="t('routine.oss.aliyun_access_key_id')" type="string"/>
              <FormItem v-model="state.formData.aliyun.access_key_secret" :label="t('routine.oss.aliyun_access_key_secret')" type="string"/>
              <FormItem v-model="state.formData.aliyun.bucket" :label="t('routine.oss.aliyun_bucket')" type="string"/>
              <FormItem v-model="state.formData.aliyun.cdn_url" :label="t('routine.oss.aliyun_cdn_url')" type="string"/>
              <FormItem
                  v-model="state.formData.aliyun.url"
                  :data="{
                      content: state.aliyun_region
                  }"
                  :label="t('routine.oss.aliyun_url')"
                  type="select"
              />
            </div>
            <div v-if="state.formData.storage_driver === 'cos'">
              <el-divider content-position="left">{{ t('routine.oss.cos_config') }}</el-divider>
              <FormItem v-model="state.formData.cos.secret_id" :label="t('routine.oss.cos_secret_id')" type="string"/>
              <FormItem v-model="state.formData.cos.secret_key" :label="t('routine.oss.cos_secret_key')" type="string"/>
              <FormItem v-model="state.formData.cos.bucket" :label="t('routine.oss.cos_bucket')" type="string"/>
              <FormItem v-model="state.formData.cos.cdn_url" :label="t('routine.oss.cos_cdn_url')" type="string"/>
              <FormItem
                  v-model="state.formData.cos.url"
                  :data="{
                      content: state.cos_region
                  }"
                  :label="t('routine.oss.cos_url')"
                  type="select"
              />
            </div>
            <div v-if="state.formData.storage_driver === 'qiniu'">
              <el-divider content-position="left">{{ t('routine.oss.qiniu_config') }}</el-divider>
              <FormItem v-model="state.formData.qiniu.access_key" :label="t('routine.oss.qiniu_access_key')" type="string"/>
              <FormItem v-model="state.formData.qiniu.secret_key" :label="t('routine.oss.qiniu_secret_key')" type="string"/>
              <FormItem v-model="state.formData.qiniu.bucket" :label="t('routine.oss.qiniu_bucket')" type="string"/>
              <FormItem v-model="state.formData.qiniu.cdn_url" :label="t('routine.oss.qiniu_cdn_url')" type="string"/>
              <FormItem v-model="state.formData.qiniu.url"
                  :data="{
                      content: state.qiniu_region
                  }"
                  :label="t('routine.oss.qiniu_url')"
                  type="select"
              />
            </div>
            <el-button v-blur :loading="state.loading" type="primary" @click="submitOssConfig()">
              {{ t('Save') }}
            </el-button>
          </el-form>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script lang="ts" setup>
import {reactive} from 'vue'
import {useI18n} from 'vue-i18n'
import {getConfig, saveConfig} from '/@/api/backend/routine/oss'
import FormItem from '/@/components/formItem/index.vue'

const {t} = useI18n()
const state = reactive({
  aliyun_region: {
    'oss-cn-hangzhou': '??????1(??????)',
    'oss-cn-shanghai': '??????2(??????)',
    'oss-cn-nanjing': '??????5(??????-????????????)',
    'oss-cn-fuzhou': '??????6(??????-????????????)',
    'oss-cn-qingdao': '??????1(??????)',
    'oss-cn-beijing': '??????2(??????)',
    'oss-cn-zhangjiakou': '??????3(?????????)',
    'oss-cn-huhehaote': '??????5(????????????)',
    'oss-cn-wulanchabu': '??????6(????????????)',
    'oss-cn-shenzhen': '??????1(??????)',
    'oss-cn-heyuan': '??????2(??????)',
    'oss-cn-guangzhou': '??????3(??????)',
    'oss-cn-chengdu': '??????1(??????)',
    'oss-cn-hongkong': '????????????',
    'oss-us-west-1': '??????(??????)*',
    'oss-us-east-1': '??????(????????????)*',
    'oss-ap-northeast-1': '??????(??????)*',
    'oss-ap-northeast-2': '??????(??????)',
    'oss-ap-southeast-1': '?????????*',
    'oss-ap-southeast-2': '????????????(??????)*',
    'oss-ap-southeast-3': '????????????(?????????)*',
    'oss-ap-southeast-5': '???????????????(?????????)*',
    'oss-ap-southeast-6': '?????????(?????????)',
    'oss-ap-southeast-7': '??????(??????)',
    'oss-ap-south-1': '??????(??????)*',
    'oss-eu-central-1': '??????(????????????)*',
    'oss-eu-west-1': '??????(??????)',
    'oss-me-east-1': '?????????(??????)*'
  },
  cos_region: {
    'ap-beijing-1': '????????????',
    'ap-beijing': '??????',
    'ap-nanjing': '??????',
    'ap-shanghai': '??????',
    'ap-guangzhou': '??????',
    'ap-chengdu': '??????',
    'ap-chongqing': '??????',
    'ap-shenzhen-fsi': '????????????',
    'ap-shanghai-fsi': '????????????',
    'ap-beijing-fsi': '????????????',
    'ap-hongkong': '????????????',
    'ap-singapore': '?????????',
    'ap-mumbai': '??????',
    'ap-jakarta': '?????????',
    'ap-seoul': '??????',
    'ap-bangkok': '??????',
    'ap-tokyo': '??????',
    'na-siliconvalley': '??????(??????)',
    'na-ashburn': '????????????(??????)',
    'na-toronto': '?????????',
    'sa-saopaulo': '?????????',
    'eu-frankfurt': '????????????',
    'eu-moscow': '?????????',
  },
  qiniu_region: {
    'https://upload.qiniup.com': '??????-??????',
    'https://upload-cn-east-2.qiniup.com': '??????-??????2',
    'https://upload-z1.qiniup.com': '??????-??????',
    'https://upload-z2.qiniup.com': '??????-??????',
    'https://upload-na0.qiniup.com': '??????-?????????',
    'https://upload-as0.qiniup.com': '??????-?????????(????????????)',
    'https://upload-ap-northeast-1.qiniup.com': '??????-??????',
  },
  loading: false,
  formData: {
    // ????????????
    storage_driver: 'local',
    // ?????????
    aliyun: {
      bucket: '',
      access_key_id: '',
      access_key_secret: '',
      url: '',
      cdn_url: ''
    },
    // ?????????
    cos: {
      bucket: '',
      secret_id: '',
      secret_key: '',
      url: '',
      cdn_url: ''
    },
    // ?????????
    qiniu: {
      bucket: '',
      access_key: '',
      secret_key: '',
      url: '',
      cdn_url: ''
    }
  }
})

const submitOssConfig = () => {
  state.loading = true
  saveConfig('base', state.formData).finally(() => {
    state.loading = false
  })
}

const init = () => {
  state.loading = false
  getConfig()
      .then((res) => {
        if (Object.keys(res.data).length > 0) {
          state.formData = res.data;
        }
      })
      .finally(() => {
        state.loading = false
      })
}
init()
</script>

<style lang="scss" scoped>
</style>
