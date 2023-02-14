import createAxios from '/@/utils/axios';
import {useSiteConfig} from '/@/stores/siteConfig';
import {ElNotification, UploadRawFile} from 'element-plus';
import {randomNum, shortUuid} from '/@/utils/random';
import {fullUrl} from '/@/utils/common';
import COS from 'cos-js-sdk-v5';
import jsSHA from 'jssha';

export const state = () => {
    const siteConfig = useSiteConfig();
    return siteConfig.upload.mode == 'local' ? 'disable' : 'enable';
}

export async function fileUpload(fd: FormData, params: anyObj = {}): ApiPromise {
    const siteConfig = useSiteConfig();
    const file = fd.get('file') as UploadRawFile;
    const sha1 = await getFileSha1(file);
    const fileKey = getSaveName(file, sha1);
    if (siteConfig.upload.mode == 'cos') {
        return new Promise(async (resolve, reject) => {
            const cos = new COS({
                getAuthorization: function (options, callback) {
                    createAxios({
                        url: '/admin/routine.oss/cosRefreshToken',
                        method: 'get',
                    }).then((res: any) => {
                        callback({
                            TmpSecretId: res.data.tmpSecretId,
                            TmpSecretKey: res.data.tmpSecretKey,
                            SecurityToken: res.data.sessionToken,
                            StartTime: res.data.startTime,
                            ExpiredTime: res.data.expiredTime,
                        });
                    });
                },
            });
            cos.uploadFile({
                Bucket: siteConfig.upload.params.bucket,
                Region: siteConfig.upload.params.region,
                Key: fileKey,
                Body: file,
                SliceSize: 1024 * 1024 * 5,
                onFileFinish: function (err, data, options) {
                    const fileUrl = '/' + options.Key;
                    if (!err) {
                        uploadCallback(file, fileUrl, sha1, siteConfig);
                        resolve(getResolve(fileUrl));
                    } else {
                        ElNotification({
                            type: 'error',
                            message: err.message,
                        });
                        reject({
                            code: 0,
                            data: err,
                            msg: err.message,
                            time: Date.now(),
                        });
                    }
                },
            })
        }) as ApiPromise;
    }
    fd.append('key', fileKey);
    for (const key in siteConfig.upload.params) {
        fd.append(key, siteConfig.upload.params[key]);
    }
    if (siteConfig.upload.mode == 'aliyun') {
        // 接口要求file排在最后
        fd.delete('file');
        fd.append('file', file);
    }
    return new Promise(async (resolve, reject) => {
        let showMsgObj = siteConfig.upload.mode == 'aliyun'
            ? {}
            : {showCodeMessage: false};
        createAxios({
            url: siteConfig.upload.url,
            method: 'POST',
            data: fd,
            params: params,
        }, showMsgObj).then(() => {
            const fileUrl = '/' + fileKey;
            uploadCallback(file, fileUrl, sha1, siteConfig);
            resolve(getResolve(fileUrl));
        }).catch((res) => {
            // 七牛的code不会等于1，失败或成功都会被axios封装拦截到此处
            if (res.code && res.error) {
                ElNotification({
                    type: 'error',
                    message: res.error,
                });
                reject({
                    code: 0,
                    data: res,
                    msg: res.error,
                    time: Date.now(),
                });
            } else {
                const fileUrl = '/' + res.key;
                uploadCallback(file, fileUrl, sha1, siteConfig);
                resolve(getResolve(fileUrl, res.error));
            }
        })
    }) as ApiPromise;
}

export function getSaveName(file: UploadRawFile, sha1: string) {
    const fileSuffix = file.name.substring(file.name.lastIndexOf('.') + 1);
    const fileName = file.name.substring(0, file.name.lastIndexOf('.'));
    const dateObj = new Date();

    const replaceArr: anyObj = {
        '{topic}': 'default',
        '{year}': dateObj.getFullYear(),
        '{mon}': ('0' + (dateObj.getMonth() + 1)).slice(-2),
        '{day}': dateObj.getDate(),
        '{hour}': dateObj.getHours(),
        '{min}': dateObj.getMinutes(),
        '{sec}': dateObj.getSeconds(),
        '{random}': shortUuid(),
        '{random32}': randomNum(32, 32),
        '{filename}': fileName.substring(0, 100),
        '{suffix}': fileSuffix,
        '{.suffix}': '.' + fileSuffix,
        '{filesha1}': sha1,
    };
    const replaceKeys = Object.keys(replaceArr).join('|');
    const siteConfig = useSiteConfig();

    const saveName = siteConfig.upload.savename[0] == '/' 
        ? siteConfig.upload.savename.slice(1) 
        : siteConfig.upload.savename;

    return saveName.replace(new RegExp(replaceKeys, 'gm'), (match) => {
        return replaceArr[match];
    });
}

function uploadCallback(file: UploadRawFile, fileUrl: string, sha1: string, siteConfig: anyObj) {
    createAxios({
        url: '/admin/routine.oss/callback',
        method: 'POST',
        data: {
            url: fileUrl,
            name: file.name,
            size: file.size,
            type: file.type,
            sha1: sha1,
            storage: siteConfig.upload.mode
        },
    });
}

function getResolve(fileUrl: string, msg = '') {
    return {
        code: 1,
        data: {
            file: {
                full_url: fullUrl(fileUrl),
                url: fileUrl,
            },
        },
        msg: msg,
        time: Date.now(),
    };
}

async function getFileSha1(file: UploadRawFile) {
    const shaObj = new jsSHA('SHA-1', 'ARRAYBUFFER');
    shaObj.update(await file.arrayBuffer());
    return shaObj.getHash('HEX');
}
