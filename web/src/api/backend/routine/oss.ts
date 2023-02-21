import createAxios from '/@/utils/axios'

export function getConfig() {
    return createAxios({
        url: '/admin/routine.oss/getConfig',
        method: 'get',
    })
}

export function saveConfig(action: string, data: anyObj) {
    return createAxios(
        {
            url: '/admin/routine.oss/saveConfig',
            method: 'post',
            data: data,
        },
        {
            showSuccessMessage: true,
        }
    )
}
